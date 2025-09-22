<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\DBAL\Connection;

class ProfilController extends AbstractController
{
    #[Route('/profil', name: 'profil')]
    public function index(Connection $conn): Response
    {
        // ⚠️ Ici on récupère directement l'utilisateur "alice.b1@example.com"
        $query = 'SELECT user_firstname, user_lastname, user_level 
                  FROM users 
                  WHERE user_email = :email';

        $stmt = $conn->prepare($query);
        $result = $stmt->executeQuery(['email' => 'alice.b1@example.com']);

        $dbUser = $result->fetchAssociative();

        if (!$dbUser) {
            throw $this->createNotFoundException("Utilisateur introuvable en base");
        }

        return $this->render('profil/profil.html.twig', [
            'firstname' => $dbUser['user_firstname'],
            'lastname'  => $dbUser['user_lastname'],
            'level'     => $dbUser['user_level'],
        ]);
    }
}
