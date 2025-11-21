<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController extends AbstractController
{
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

    #[Route('/login-handler', name: 'login_handler', methods: ['POST'])]
    public function loginHandler(): never
    {
        // Cette méthode ne sera jamais appelée car l'authenticator intercepte la requête
        throw new \LogicException('Cette méthode devrait être gérée par l\'authenticator.');
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): never
    {
        // Cette méthode ne sera jamais appelée, Symfony gère la déconnexion
        throw new \LogicException('Cette méthode devrait être interceptée par le logout.');
    }
}