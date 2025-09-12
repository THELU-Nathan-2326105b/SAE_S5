<?php

class ForumController
{
    /**
     * Affiche la vue de la page forum.
     * Cette méthode charge et affiche la vue associée à la page forum de l'application.
     *
     * @return void
     */
    public function display()
    {
        ViewHandler::show("ForumView");
    }


}