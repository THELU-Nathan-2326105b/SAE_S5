<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ProfilController extends AbstractController
{
    #[Route('/profil', name: 'profil')]
    public function index(Request $request, UsersRepository $usersRepository): Response
    {
        $session = $request->getSession();
        $userSession = $session->get('user');

        if (!$userSession) {
            // ⚠️ pas connecté → redirection vers login
            return $this->redirectToRoute('login');
        }

        // Utilisation de Doctrine pour récupérer l’utilisateur
        /** @var Users|null $dbUser */
        $dbUser = $usersRepository->find($userSession['id']);

        if (!$dbUser) {
            throw $this->createNotFoundException("Utilisateur introuvable en base");
        }

        return $this->render('profil/profil.html.twig', [
            'firstname' => $dbUser->getUserFirstname(),
            'lastname'  => $dbUser->getUserLastname(),
            'level'     => $dbUser->getUserLevel(),
            'mail'      => $dbUser->getUserEmail(),
            'role'      => $dbUser->getUserRole(),
        ]);
    }
}
