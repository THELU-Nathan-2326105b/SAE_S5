<?php

namespace App\Controller;

use App\Repository\ForumRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PlanningController extends AbstractController
{
    #[Route('/planning', name: 'planning')]
    public function index(ForumRepository $forumRepository): Response
    {
        $forums = $forumRepository->findAll();
        
        return $this->render('planning/planningalgo.html.twig', [
            'forums' => $forums,
        ]);
    }
}
