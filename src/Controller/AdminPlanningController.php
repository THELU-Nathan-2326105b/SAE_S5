<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\DBAL\Connection;
use App\Service\PlanningAlgo;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $companies = $this->fetchCompaniesForForum($conn, $selectedForumId);

        return $this->render('admin/creerplanning.html.twig', [
            'forums' => $forums,
            'selected_forum_id' => $selectedForumId,
            'undistributed' => $undistributed,
            'undistributed_count' => count($undistributed),
            'companies' => $companies,
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

        $forums = $conn->fetchAllAssociative('SELECT * FROM forum ORDER BY forum_id');

        $undistributed = $this->fetchUndistributed($conn, $forumId);
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
        $companies = $this->fetchCompaniesForForum($conn, $forumId);

        return $this->render('admin/creerplanning.html.twig', [
            'forums' => $forums,
            'selected_forum_id' => $forumId,
            'undistributed' => $undistributed,
            'undistributed_count' => count($undistributed),
            'companies' => $companies,
        ]);
    }

    #[Route('/admin/creerplanning/export', name: 'admin_creerplanning_export', methods: ['GET'])]
    /**
     * Exporte le planning en fichier CSV
     * 
     * @param Request $request Requête HTTP contenant l'ID du forum et le nom de l'entreprise optionnel
     * @param Connection $conn Connexion à la base de données
     * @return Response Fichier CSV à télécharger
     */
    public function exportPlanning(Request $request, Connection $conn): Response
    {
        $forumId = $request->query->getInt('forum_id');
        $company = trim((string)$request->query->get('company', ''));

        if ($forumId <= 0) {
            $this->addFlash('error', 'Forum invalide pour l’export CSV.');
            return $this->redirectToRoute('admin_creerplanning');
        }

        $filename = 'planning_forum_' . $forumId . ($company ? '_'.preg_replace('/[^a-zA-Z0-9_-]/', '_', $company) : '_toutes') . '.csv';

        $response = new StreamedResponse(function() use ($conn, $forumId, $company) {
            $handle = fopen('php://output', 'w');
            // En-têtes
            fputcsv($handle, ['Étudiant', 'Entreprise', 'Créneau']);

            $sql = 'SELECT u.user_firstname, u.user_lastname, a.company_name, a.appointment_time
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
                fputcsv($handle, [$fullname, $row['company_name'], $slot]);
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
