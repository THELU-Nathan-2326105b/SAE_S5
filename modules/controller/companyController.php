<?php


/**
 * Contrôleur de la page des entreprises.
 * Ce contrôleur est responsable de l'affichage de la vue de la page des entreprises.
 */
class companyController
{
    private $companyModel;

    public function __construct() {
        $this->companyModel = new companyModel(); // Créer une instance du modèle
    }
    /**
     * Affiche la vue de la page des entreprises.
     * Cette méthode charge et affiche la vue associée à la page des entreprises.
     *
     * @return void
     */
    public function show(): void
    {

        $companies = $this->companyModel->getCompanies();
        $selected = [];
        // Vérifie si le formulaire a été soumis
        if (!empty($_POST['entreprises'])) {
            $selected = $_POST['entreprises'];
        }

        // Passe les données à la vue
        ViewHandler::show("company/companyView", [
            'companies' => $companies,
            'selected' => $selected
        ]);
    }
}

