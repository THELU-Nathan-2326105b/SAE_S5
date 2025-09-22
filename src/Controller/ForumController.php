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
    public function index(Request $request,Connection $conn): Response
    {
        $query = 'SELECT company_name, company_description FROM company';
        $stmt = $conn->prepare($query);

        $result = $stmt->executeQuery();

        $companies = $result->fetchAllAssociative();

        $selected = $request->request->all('entreprises');

        return $this->render('forum/forum.html.twig', [
            'companies' => $companies,
            'selected'  => $selected,
        ]);
    }
}
