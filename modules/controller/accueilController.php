<?php

/**
 * Contrôleur de la page d'accueil.
 * Ce contrôleur est responsable de l'affichage de la vue de la page d'accueil.
 */
class accueilController
{
    /**
     * Affiche la vue de la page d'accueil.
     * Cette méthode charge et affiche la vue associée à la page d'accueil de l'application.
     * 
     * @return void
     */
    public function login()
    {
        ViewHandler::show("../view/accueilView.php");
    }
}
?>
