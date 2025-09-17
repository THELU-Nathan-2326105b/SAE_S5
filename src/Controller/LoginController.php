<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function index(): Response
    {
        return $this->render('login/login.html.twig');
    }

    #[Route('/forgot-password', name: 'forgot-password')]
    public function forgotPassword(): Response
    {
        return $this->render('login/forgotpwd.html.twig');
    }
}
