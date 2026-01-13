<?php

/**
 * Contrôleur Apropos
 * 
 * Affiche la page "À propos" du site
 * Informations sur le projet et l'application
 * 
 * @package App\Controller
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Class Apropos
 * 
 * Contrôleur pour la page "À propos".
 */
final class Apropos extends AbstractController
{
    /**
     * Affiche la page "À propos"
     * 
     * @return Response
     * @Route('/a-propos', name: 'a-propos')
     */
    #[Route('/a-propos', name: 'a-propos')]
    public function index(): Response
    {
        return $this->render('apropos.html.twig');
    }

}
