<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MentionLegale extends AbstractController
{
    #[Route('/mentions-legales', name: 'mentions-legales')]
    public function index(): Response
    {
        return $this->render('mentionlegale.html.twig');
    }
}
