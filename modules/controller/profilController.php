<?php

/**
 * Contrôleur du profil.
 * Ce contrôleur est responsable de l'affichage de la vue du profil.
 */
class profilController
{
    private $userModel;

    public function __construct() {
        $this->userModel = new profilModel(); // Créer une instance du modèle
    }
    /**
     * Affiche la vue du profil.
     * Cette méthode charge et affiche la vue associée au profil de l'application.
     * 
     * @return void
     */
    public function display()
    {
        $_SESSION['mail'] = "alice.b1@example.com";
        $prenom= $this->userModel->getInfosProfil($_SESSION['mail'])['user_firstname'];
        $nom= $this->userModel->getInfosProfil($_SESSION['mail'])['user_lastname'];
        $annee = $this->userModel->getInfosProfil($_SESSION['mail'])['user_level'];
        ViewHandler::show("../view/profilView", ['prenom' => $prenom, 'nom' => $nom, 'annee' => $annee]);
    }
}
?>
