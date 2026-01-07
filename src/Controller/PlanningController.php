<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PlanningController extends AbstractController
{
    #[Route('/planning', name: 'planning')]
    public function index(Connection $conn): Response
    {
        $forums = $conn->fetchAllAssociative('SELECT * FROM forum ORDER BY forum_id');
        
        return $this->render('planning/planningalgo.html.twig', [
            'forums' => $forums,
        ]);
    }
}
