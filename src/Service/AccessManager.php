<?php

namespace App\Service;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class AccessManager
{
    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $router,
        private RequestStack $requestStack
    ) {}

    /**
     * Vérifie si l'utilisateur est admin.
     * S'il ne l'est pas, ajoute un flash et retourne une RedirectResponse.
     * Sinon, retourne null.
     */
    public function checkAdminAccess(): ?Response
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return null;
        }
        $session = $this->requestStack->getSession();
        if ($session instanceof Session) {
            $session->getFlashBag()->add('error', 'Accès réservé aux administrateurs. Redirection effectuée.');
        }
        return new RedirectResponse($this->router->generate('home')); 

    }
}