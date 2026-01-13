<?php

/**
 * Contrôleur MentionLegale
 * 
 * Affiche les mentions légales de l'application
 * Informations légales et conditions d'utilisation
 * 
 * @package App\Controller
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Class MentionLegale
 * 
 * Contrôleur pour la page des mentions légales.
 */
final class MentionLegale extends AbstractController
{
    /**
     * Affiche la page des mentions légales
     * 
     * @return Response
     * @Route('/mentions-legales', name: 'mentions-legales')
     */
    #[Route('/mentions-legales', name: 'mentions-legales')]
    public function index(): Response
    {
        return $this->render('mentionlegale.html.twig');
    }
}
