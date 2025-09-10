<?php

class ForumController
{
    /**
     * Affiche la vue de la page d'accueil.
     * Cette méthode charge et affiche la vue associée à la page d'accueil de l'application.
     *
     * @return void
     */
    public function display()
    {
        ViewHandler::show("ForumView");
    }


}