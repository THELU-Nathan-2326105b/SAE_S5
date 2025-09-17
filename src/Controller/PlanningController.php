<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PlanningController extends AbstractController
{
    #[Route('/planning', name: 'planning')]
    public function index(): Response
    {
        return $this->render('planning/planning.html.twig');
    }
}
