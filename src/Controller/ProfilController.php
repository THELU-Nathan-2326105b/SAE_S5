<?php

namespace App\Controller;

use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProfilController extends AbstractController
{
    #[Route('/profil', name: 'profil')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]  // Protection de la route
    public function index(): Response
    {
        // Récupération de l'utilisateur connecté via le système Symfony
        /** @var Users $user */
        $user = $this->getUser();

        // Si getUser() retourne null (ne devrait pas arriver grâce à IsGranted)
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        return $this->render('profil/profil.html.twig', [
            'firstname' => $user->getUserFirstname(),
            'lastname'  => $user->getUserLastname(),
            'level'     => $user->getUserLevel(),
            'mail'      => $user->getUserEmail(),
            'role'      => $user->getUserRole(),
        ]);
    }
}