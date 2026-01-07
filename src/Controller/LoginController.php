<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

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
    public function loginHandler(Request $request, UsersRepository $usersRepository): Response
    {
        // Récupération des champs du formulaire
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $recaptchaResponse = $request->request->get('g-recaptcha-response');

        // Étape 1 : Vérification reCAPTCHA v3
        $secretKey = '6LeygQAsAAAAAK-6I0IrVAZGjUk02p4Iw5oguPHq'; // ta clé secrète v3

        $response = $this->client->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $secretKey,
                'response' => $recaptchaResponse,
            ],
        ]);

        $result = $response->toArray();
        //dd($result, $recaptchaResponse);    

        // Vérifie la validité du token et le score minimal
        if (
            !$result['success'] ||
            ($result['score'] ?? 0) < 0.5 ||
            ($result['action'] ?? '') !== 'login'
        ) {
            return $this->render('login/login.html.twig', [
                'error' => 'Vérification reCAPTCHA échouée. Veuillez réessayer.',
            ]);
        }

        // Étape 2 : Vérification des identifiants
        $user = $usersRepository->findOneBy(['user_email' => $email]);

        if (!$user || !password_verify($password, $user->getUserPwd())) {
            return $this->render('login/login.html.twig', [
                'error' => 'Email ou mot de passe incorrect.',
            ]);
        }

        // Étape 3 : Sauvegarde des infos utilisateur en session
        $session = $request->getSession();
        $session->set('user', [
            'id'        => $user->getId(),
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
        $request->getSession()->invalidate();
        return $this->redirectToRoute('login');
    }
}
