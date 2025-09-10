<?php

namespace blog\controller;
use ViewHandler;

/**
 * Contrôleur de la page d'accueil.
 * Ce contrôleur est responsable de l'affichage de la vue de la page d'accueil.
 */
class HomeController
{
    /**
     * Affiche la vue de la page d'accueil.
     * Cette méthode charge et affiche la vue associée à la page d'accueil de l'application.
     * 
     * @return void
     */
    public function display()
    {
        ViewHandler::show("HomeView");
    }

    /**
     * Redirige vers la page du forum.
     *
     * @return void
     */
    public function handleForumClick()
    {
        header("Location: /index.php?controller=Forum&action=display");
        exit();
    }
}
?>
