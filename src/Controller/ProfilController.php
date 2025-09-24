<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\DBAL\Connection;

class ProfilController extends AbstractController
{
    #[Route('/profil', name: 'profil')]
    public function index(Request $request, Connection $conn): Response
    {
        $session = $request->getSession();
        $userSession = $session->get('user');

        if (!$userSession) {
            // ⚠️ pas connecté → redirection vers login
            return $this->redirectToRoute('login');
        }

        // récupérer les infos utilisateur à jour depuis la BDD
        $query = 'SELECT user_firstname, user_lastname, user_level, user_email, user_role
                  FROM users 
                  WHERE user_id = :id';

        $stmt = $conn->prepare($query);
        $result = $stmt->executeQuery(['id' => $userSession['id']]);

        $dbUser = $result->fetchAssociative();

        if (!$dbUser) {
            throw $this->createNotFoundException("Utilisateur introuvable en base");
        }

        return $this->render('profil/profil.html.twig', [
            'firstname' => $dbUser['user_firstname'],
            'lastname'  => $dbUser['user_lastname'],
            'level'     => $dbUser['user_level'],
            'mail'      => $dbUser['user_email'],
            'role'      => $dbUser['user_role'],
        ]);
    }
}
