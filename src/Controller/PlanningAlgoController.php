<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\DBAL\Connection;
use App\Service\PlanningAlgo;

final class PlanningAlgoController extends AbstractController
{
    #[Route('/planning-algo', name: 'planning-algo')]
    public function view(Connection $conn): Response
    {
        $forums = $conn->fetchAllAssociative('SELECT * FROM forum ORDER BY forum_id');

        return $this->render('planning/planningalgo.html.twig', [
            'forums' => $forums,
        ]);
    }

    #[Route('/planning/run', name: 'planning_algo_run')]
    public function run(Request $request, Connection $conn): Response
    {
        $forum_id = $request->query->getInt('forum_id');

        // Call the new planning generator which reads data and writes appointments
        $result = PlanningAlgo::generatePlanning($conn, $forum_id);

        $forums = $conn->fetchAllAssociative('SELECT * FROM forum ORDER BY forum_id');

        // Pass result to the template: message, count and appointments list
        return $this->render('planning/planningalgo.html.twig', [
            'forums' => $forums,
            'planning_result' => $result,
            'appointments' => $result['appointments'] ?? [],
            'appointments_count' => $result['count'] ?? 0,
        ]);
    }

    #[Route('/planning/reset', name: 'planning_algo_reset')]
    public function reset(Request $request, Connection $conn): Response
    {
        $forum_id = $request->query->get('forum_id');

        if ($forum_id && is_numeric($forum_id) && $forum_id > 0) {
            try {
                PlanningAlgo::resetAppointments($conn, (int)$forum_id);
                $this->addFlash('success', 'Les rendez-vous du forum ont été réinitialisés avec succès.');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Erreur lors de la réinitialisation : ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Veuillez sélectionner un forum valide.');
        }

        $forums = $conn->fetchAllAssociative('SELECT * FROM forum ORDER BY forum_id');

        return $this->render('planning/planningalgo.html.twig', [
            'forums' => $forums,
        ]);
    }
}
