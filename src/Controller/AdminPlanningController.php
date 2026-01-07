<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\DBAL\Connection;
use App\Service\PlanningAlgo;

final class AdminPlanningController extends AbstractController
{
    #[Route('/admin/creerplanning', name: 'admin_creerplanning', methods: ['GET'])]
    public function createPlanning(Connection $conn): Response
    {
        $forums = $conn->fetchAllAssociative('SELECT * FROM forum ORDER BY forum_id');

        return $this->render('admin/creerplanning.html.twig', [
            'forums' => $forums,
        ]);
    }

    #[Route('/admin/creerplanning/run', name: 'admin_creerplanning_run', methods: ['GET'])]
    public function runPlanning(Request $request, Connection $conn): Response
    {
        $forumId = $request->query->getInt('forum_id');

        $result = PlanningAlgo::generatePlanning($conn, $forumId);

        $forums = $conn->fetchAllAssociative('SELECT * FROM forum ORDER BY forum_id');

        return $this->render('admin/creerplanning.html.twig', [
            'forums' => $forums,
            'planning_result' => $result,
            'appointments' => $result['appointments'] ?? [],
            'appointments_count' => $result['count'] ?? 0,
        ]);
    }

    #[Route('/admin/creerplanning/reset', name: 'admin_creerplanning_reset', methods: ['GET'])]
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

        return $this->render('admin/creerplanning.html.twig', [
            'forums' => $forums,
        ]);
    }
}
