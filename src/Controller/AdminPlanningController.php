<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\PlanningAlgo;
use App\Service\CvArchiver;
use App\Entity\Company;
use App\Entity\Forum;



use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
/**
 * AdminPlanningController
 *
 * Contrôleur responsable de la gestion du planning des rendez-vous en admin.
 * Permet de créer, réinitialiser et exporter le planning.
 *
 * @package App\Controller
 */
final class AdminPlanningController extends AbstractController
{
    /**
     * Exporte les CV des utilisateurs d'un forum pour une entreprise spécifique
     * ou pour toutes les entreprises si aucun nom n'est fourni.
     *
     * @param Request $request Requête HTTP contenant les paramètres forum_id et company
     * @param EntityManagerInterface $em Gestionnaire d'entités pour accéder à la base de données
     * @param CvArchiver $cvArchiver Service pour la génération des archives ZIP des CV
     * @return Response Fichier ZIP contenant les CV ou message d'erreur
     */
    #[Route('/admin/planning/export-cv', name: 'admin_export_cv', methods: ['GET'])]
    public function exportCompanyCvs(
        Request $request,
        EntityManagerInterface $em,
        CvArchiver $cvArchiver
    ): Response {
        $forumId = $request->query->getInt('forum_id');
        $companyName = trim((string) $request->query->get('company', ''));

        if ($forumId <= 0) {
            $this->addFlash('error', 'Paramètres invalides.');
            return $this->redirectToRoute('admin_creerplanning');
        }

        $forumEntity = $em->getRepository(Forum::class)->find($forumId);
        if (!$forumEntity instanceof Forum) {
            $this->addFlash('error', 'Forum introuvable.');
            return $this->redirectToRoute('admin_creerplanning');
        }

        $usersWithCv = [];
        $missingCvUsers = [];
        $fileMissingUsers = [];

        try {
            $appointments = $cvArchiver->getForumAppointments($forumEntity);

            // Filtrer par entreprise si nécessaire
            if ($companyName !== '') {
                $companyEntity = $em->getRepository(Company::class)->findOneBy([
                    'company_name' => $companyName
                ]);
                if (!$companyEntity) {
                    throw new \RuntimeException('Entreprise introuvable.');
                }

                $appointments = array_filter($appointments, fn($appointment) =>
                    $appointment->getCompanyName() === $companyName
                );

                $zipFileName = 'forum_' . $forumId . '_' . preg_replace('/[^A-Za-z0-9_]/', '_', $companyName) . '.zip';
            } else {
                $zipFileName = 'forum_' . $forumId . '_all_cv.zip';
            }

            $appointments = array_filter($appointments, function($appointment) {
                $duration = (int) $appointment->getDuration(); // cast en int pour être sûr
                return $duration >= 5;
            });



            // Classer les utilisateurs selon CV existant ou manquant
            foreach ($appointments as $appointment) {
                $user = $appointment->getUser();
                if ($user instanceof \App\Entity\Users) {
                    $fullname = trim(($user->getUserFirstname() ?? '-') . ' ' . ($user->getUserLastname() ?? '-'));
                    $cvPath = $cvArchiver->getPhysicalPath($user);

                    if ($user->getUserUrlCv() === null) {
                        $missingCvUsers[] = $fullname;
                    } elseif (!$cvPath || !file_exists($cvPath)) {
                        $fileMissingUsers[] = $fullname;
                    } else {
                        $usersWithCv[] = $user;
                    }
                } else {
                    $missingCvUsers[] = 'Utilisateur inconnu';
                }
            }

            // --- Génération du ZIP avec README si nécessaire --- //
            if (!empty($usersWithCv)) {
                if ($companyName !== '') {
                    $zipFilePath = $cvArchiver->generateCompanyZip(
                        $companyEntity,
                        $forumEntity,
                        $usersWithCv,
                        $missingCvUsers,
                        $fileMissingUsers
                    );
                } else {
                    $zipFilePath = $cvArchiver->generateForumZip(
                        $forumEntity,
                        $usersWithCv,
                        $missingCvUsers,
                        $fileMissingUsers
                    );
                }
            } else {
                $zipFilePath = null; // Aucun CV à exporter
            }

            if (!$zipFilePath || !file_exists($zipFilePath)) {
                $this->addFlash('error', 'Aucun CV n’a pu être exporté.');
                return $this->redirectToRoute('admin_creerplanning', ['forum_id' => $forumId]);
            }

            $response = new BinaryFileResponse($zipFilePath);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $zipFileName);
            $response->deleteFileAfterSend(true);

            return $response;

        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            return $this->redirectToRoute('admin_creerplanning', ['forum_id' => $forumId]);
        }
    }

    /**
     * Affiche la page de création du planning
     *
     * @param Connection $conn Connexion à la base de données
     * @return Response Page de gestion du planning
     */
    #[Route('/admin/creerplanning', name: 'admin_creerplanning', methods: ['GET'])]
    public function createPlanning(Connection $conn): Response
    {
        $forums = $conn->fetchAllAssociative('SELECT * FROM forum ORDER BY forum_id');
        $selectedForumId = (int)($forums[0]['forum_id'] ?? 0);

        $undistributed = $this->fetchUndistributed($conn, $selectedForumId);

        usort($undistributed, fn($a,$b) => strcmp($a['company_name'] ?? '', $b['company_name'] ?? ''));

        $companies = $this->fetchCompaniesForForum($conn, $selectedForumId);

        $blockedCompanies = [];

        return $this->render('admin/creerplanning.html.twig', [
            'forums' => $forums,
            'selected_forum_id' => $selectedForumId,
            'undistributed' => $undistributed,
            'undistributed_count' => count($undistributed),
            'companies' => $companies,
            'blocked_companies' => $blockedCompanies
        ]);
    }


    #[Route('/admin/creerplanning/run', name: 'admin_creerplanning_run', methods: ['GET'])]
    /**
     * Exécute l'algorithme de génération du planning
     *
     * @param Request $request Requête HTTP contenant l'ID du forum
     * @param Connection $conn Connexion à la base de données
     * @return Response Page avec le résultat du planning généré
     */
    public function runPlanning(Request $request, Connection $conn): Response
    {
        $forumId = $request->query->getInt('forum_id');

        $result = PlanningAlgo::generatePlanning($conn, $forumId);

        $blockedCompanies = $result['blocked_companies'] ?? [];

        $forums = $conn->fetchAllAssociative('SELECT * FROM forum ORDER BY forum_id');

        $undistributed = $this->fetchUndistributed($conn, $forumId);

        usort($undistributed, fn($a,$b) => strcmp($a['company_name'] ?? '', $b['company_name'] ?? ''));

        $companies = $this->fetchCompaniesForForum($conn, $forumId);

        return $this->render('admin/creerplanning.html.twig', [
            'forums' => $forums,
            'selected_forum_id' => $forumId,
            'planning_result' => $result,
            'appointments' => $result['appointments'] ?? [],
            'appointments_count' => $result['count'] ?? 0,
            'undistributed' => $undistributed,
            'undistributed_count' => count($undistributed),
            'companies' => $companies,
            'blocked_companies' => $blockedCompanies
        ]);
    }

    #[Route('/admin/creerplanning/reset', name: 'admin_creerplanning_reset', methods: ['GET'])]
    /**
     * Réinitialise les rendez-vous d'un forum
     * Remet tous les rendez-vous à null et les marque comme demandes
     *
     * @param Request $request Requête HTTP contenant l'ID du forum
     * @param Connection $conn Connexion à la base de données
     * @return Response Page de gestion avec confirmation
     */
    public function resetPlanning(Request $request, Connection $conn): Response
    {
        $forumId = $request->query->getInt('forum_id');

        $blockedCompanies = [];

        if ($forumId > 0) {
            try {
                PlanningAlgo::resetAppointments($conn, $forumId);
                $this->addFlash('success', 'Les rendez-vous du forum ont été réinitialisés avec succès.');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Erreur lors de la réinitialisation : ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Veuillez sélectionner un forum valide.');
        }

        $forums = $conn->fetchAllAssociative('SELECT * FROM forum ORDER BY forum_id');

        $undistributed = $this->fetchUndistributed($conn, $forumId);

        usort($undistributed, fn($a,$b) => strcmp($a['company_name'] ?? '', $b['company_name'] ?? ''));

        $companies = $this->fetchCompaniesForForum($conn, $forumId);

        return $this->render('admin/creerplanning.html.twig', [
            'forums' => $forums,
            'selected_forum_id' => $forumId,
            'undistributed' => $undistributed,
            'undistributed_count' => count($undistributed),
            'companies' => $companies,
            'blocked_companies' => $blockedCompanies,
        ]);
    }

    #[Route('/admin/creerplanning/export', name: 'admin_creerplanning_export', methods: ['GET'])]
    /**
     * Exporte le planning en fichier CSV avec la durée des rendez-vous
     */
    public function exportPlanning(Request $request, Connection $conn): Response
    {
        $forumId = $request->query->getInt('forum_id');
        $company = trim((string)$request->query->get('company', ''));

        if ($forumId <= 0) {
            $this->addFlash('error', 'Forum invalide pour l’export CSV.');
            return $this->redirectToRoute('admin_creerplanning');
        }

        $filename = 'planning_forum_' . $forumId
            . ($company ? '_'.preg_replace('/[^a-zA-Z0-9_-]/', '_', $company) : '_toutes')
            . '.csv';

        $response = new StreamedResponse(function() use ($conn, $forumId, $company) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Étudiant',
                'Rôle',
                'Niveau',
                'Entreprise',
                'Créneau',
                'Durée (min)'
            ]);

            $sql = '
                SELECT
                u.user_firstname,
                u.user_lastname,
                u.user_role,
                u.user_level,
                a.company_name,
                a.appointment_time,
                a.duration
                FROM appointment a
                LEFT JOIN users u ON u.user_id = a.user_id
                WHERE a.forum_id = :forumId';

            $params = ['forumId' => $forumId];
            if ($company !== '') {
                $sql .= ' AND a.company_name = :company';
                $params['company'] = $company;
            }
            $sql .= ' ORDER BY a.company_name, (a.appointment_time IS NULL), a.appointment_time';

            $rows = $conn->fetchAllAssociative($sql, $params);

            foreach ($rows as $row) {
                $fullname = trim(($row['user_firstname'] ?? '') . ' ' . ($row['user_lastname'] ?? ''));
                $slot = $row['appointment_time'] ?? 'Non affecté';
                $duration = $row['duration'] ?? '-';
                fputcsv($handle, [
                    $fullname,
                    $row['user_role']  ?? '',
                    $row['user_level'] ?? '',
                    $row['company_name'],
                    $slot,
                    $duration
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    /**
     * Récupère les rendez-vous sans créneau affecté pour un forum
     *
     * @param Connection $conn Connexion à la base de données
     * @param int $forumId Identifiant du forum
     * @return array Liste des rendez-vous non distribuês
     */
    private function fetchUndistributed(Connection $conn, int $forumId): array
    {
        if ($forumId <= 0) {
            return [];
        }

        $sql = 'SELECT a.user_id, a.company_name, a.appointment_time, u.user_firstname, u.user_lastname
                FROM appointment a
                LEFT JOIN users u ON u.user_id = a.user_id
                WHERE a.forum_id = :forumId AND (a.appointment_time IS NULL)';

        return $conn->fetchAllAssociative($sql, ['forumId' => $forumId]);
    }

    /**
     * Récupère les noms des entreprises pour un forum
     *
     * @param Connection $conn Connexion à la base de données
     * @param int $forumId Identifiant du forum
     * @return array Liste des noms d'entreprises uniques
     */
    private function fetchCompaniesForForum(Connection $conn, int $forumId): array
    {
        if ($forumId <= 0) {
            return [];
        }

        $sql = 'SELECT DISTINCT company_name FROM appointment WHERE forum_id = :forumId ORDER BY company_name';
        $rows = $conn->fetchAllAssociative($sql, ['forumId' => $forumId]);
        return array_map(fn($r) => $r['company_name'], $rows);
    }
}
