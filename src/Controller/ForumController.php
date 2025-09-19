<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\DBAL\Connection;

class ForumController extends AbstractController
{
    #[Route('/forum', name: 'forum')]
    public function index(Connection $conn): Response
    {
        $companies = $conn->fetchAllAssociative('SELECT company_name, company_description FROM company');

        return $this->render('forum/forum.html.twig', [
            'companies' => $companies,
            'selected' => [],
        ]);
    }
}
