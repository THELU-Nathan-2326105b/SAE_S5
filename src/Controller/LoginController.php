<?php

namespace App\Controller;

use App\Repository\UsersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController extends AbstractController
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

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