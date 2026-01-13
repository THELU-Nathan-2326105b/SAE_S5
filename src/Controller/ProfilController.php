<?php

/**
 * Contrôleur ProfilController
 * 
 * Gére l'affichage du profil utilisateur
 * Affiche les informations du compte de l'utilisateur connecté
 * 
 * @package App\Controller
 */

namespace App\Controller;

use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Class ProfilController
 * 
 * Contrôleur pour l'affichage du profil utilisateur.
 */
class ProfilController extends AbstractController
{
    /**
     * Affiche le profil de l'utilisateur connecté
     * Récupère toutes les informations du compte
     * 
     * @return Response
     * @Route('/profil', name: 'profil')
     */
    #[Route('/profil', name: 'profil')]
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