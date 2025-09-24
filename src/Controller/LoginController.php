<?php

namespace App\Controller;

use App\Repository\UsersRepository;
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
    public function loginHandler(Request $request, UsersRepository $usersRepository): Response
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');

        // Récupérer l'utilisateur via le repository
        $user = $usersRepository->findOneBy(['user_email' => $email]);

        // Vérifier l’existence + mot de passe
        if (!$user || !password_verify($password, $user->getUserPwd())) {
            return $this->render('login/login.html.twig', [
                'error' => 'Email ou mot de passe incorrect.',
            ]);
        }

        // Sauvegarde des infos utiles en session
        $session = $request->getSession();
        $session->set('user', [
            'id'        => $user->getUserId(),
            'email'     => $user->getUserEmail(),
            'role'      => $user->getUserRole(),
            'firstname' => $user->getUserFirstname(),
            'lastname'  => $user->getUserLastname(),
        ]);

        return $this->redirectToRoute('home');
    }

    #[Route('/logout', name: 'logout')]
    public function logout(Request $request): Response
    {
        $request->getSession()->invalidate(); // vide la session
        return $this->redirectToRoute('login');
    }
}
