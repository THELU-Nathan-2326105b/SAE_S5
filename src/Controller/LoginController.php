<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * LoginController
 * 
 * Contrôleur responsable de la gestion de l'authentification.
 * Affiche le formulaire de connexion et gère la déconnexion.
 * 
 * @package App\Controller
 */
final class LoginController extends AbstractController
{
    /**
     * Affiche le formulaire de connexion
     * 
     * @param Request $request La requête HTTP courante
     * @return Response Page de connexion avec reCAPTCHA
     */
    #[Route('/login', name: 'login')]
    public function index(Request $request): Response
    {
        $error = $request->getSession()->get('login_error');
        $request->getSession()->remove('login_error');
        
        return $this->render('login/login.html.twig', [
            'error' => $error,
            'recaptcha_site_key' => $_ENV['GOOGLE_RECAPTCHA_SITE_KEY'],
        ]);
    }

    /**
     * Gestionnaire de connexion (intercepté par LoginAuthenticator)
     * Cette méthode ne doit jamais être exécutée directement
     * 
     * @throws \LogicException
     */
    #[Route('/login-handler', name: 'login_handler', methods: ['POST'])]
    public function loginHandler(): never
    {
        throw new \LogicException('This method should never be reached - intercepted by LoginAuthenticator');
    }

    /**
     * Gestionnaire de déconnexion (intercepté par logout handler)
     * Cette méthode ne doit jamais être exécutée directement
     * 
     * @throws \LogicException
     */
    #[Route('/logout', name: 'logout')]
    public function logout(): never
    {
        throw new \LogicException('This method should never be reached - intercepted by logout handler');
    }
}