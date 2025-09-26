<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class Apropos extends AbstractController
{
    #[Route('/a-propos', name: 'a-propos')]
    public function index(): Response
    {
        return $this->render('apropos.html.twig');
    }

}
