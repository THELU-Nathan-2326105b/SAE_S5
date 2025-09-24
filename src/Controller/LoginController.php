<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function index(): Response
    {
        return $this->render('login/login.html.twig', [
        'error' => null,
    ]);
    }

    #[Route('/login-handler', name: 'login_handler', methods: ['POST'])]
    public function loginHandler(Request $request, Connection $conn): Response
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');

        // Récupérer l'utilisateur dans ta BDD
        $query = 'SELECT * FROM users WHERE user_email = :email LIMIT 1';
        $stmt = $conn->prepare($query);
        $result = $stmt->executeQuery(['email' => $email]);
        $user = $result->fetchAssociative();

        if (!$user || !password_verify($password, $user['user_pwd'])) {
            return $this->render('login/login.html.twig', [
                'error' => 'Email ou mot de passe incorrect.',
            ]);
        }

        // ✅ Sauvegarde des infos utiles en session
        $session = $request->getSession();
        $session->set('user', [
            'id'    => $user['user_id'],
            'email' => $user['user_email'],
            'role'  => $user['user_role'],
            'firstname' => $user['user_firstname'],
            'lastname'  => $user['user_lastname'],
        ]);

        // Redirection vers l’accueil
        return $this->redirectToRoute('home');
    }

    #[Route('/logout', name: 'logout')]
    public function logout(Request $request): Response
    {
        $request->getSession()->invalidate();
        return $this->redirectToRoute('login');
    }
}
