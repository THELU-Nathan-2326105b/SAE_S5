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
        
    }

    #[Route('/logout', name: 'logout')]
    public function logout(Request $request): Response
    {
        // Cette méthode peut rester vide, elle sera interceptée par Symfony
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}