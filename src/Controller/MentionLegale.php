<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MentionLegale extends AbstractController
{
    #[Route('/mention-legale', name: 'mention-legale')]
    public function index(): Response
    {
        return $this->render('mentionlegale.html.twig');
    }
}
