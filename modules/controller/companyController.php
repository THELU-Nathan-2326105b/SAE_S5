<?php


/**
 * Contrôleur de la page des entreprises.
 * Ce contrôleur est responsable de l'affichage de la vue de la page des entreprises.
 */
class companyController
{
    /**
     * Affiche la vue de la page des entreprises.
     * Cette méthode charge et affiche la vue associée à la page des entreprises.
     *
     * @return void
     */
    public function show(): void
    {
        $model =new companyModel();
        $companies = $model->getCompanies();

        $selected = [];

        // Vérifie si le formulaire a été soumis
        if (!empty($_POST['entreprises'])) {
            $selected = $_POST['entreprises'];
        }

        // Passe les données à la vue
        ViewHandler::show("company/companyView", [
            'selected' => $selected,
            'companies' => $companies
        ]);
    }
}

