<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * HomeController
 * 
 * Contrôleur responsable de l'affichage de la page d'accueil.
 * 
 * @package App\Controller
 */
final class HomeController extends AbstractController
{
    /**
     * Affiche la page d'accueil du site
     * 
     * @return Response Page d'accueil rendue
     */
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
