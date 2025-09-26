<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ForumController extends AbstractController
{
    #[Route('/forum', name: 'forum')]
    public function index(Request $request, CompanyRepository $companyRepository): Response
    {
        //Récupération de toutes les entreprises
        $companies = $companyRepository->findAllOrderedByName();

        //Récupération des entreprises sélectionnées (checkboxes)
        $selected = $request->request->all('entreprises');

        return $this->render('forum/forum.html.twig', [
            'companies' => $companies,
            'selected'  => $selected,
        ]);
    }
}
