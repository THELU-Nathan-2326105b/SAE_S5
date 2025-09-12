<?php


/**
 * Class companyModel
 *
 * Cette classe représente le modèle pour la page des entreprises.
 * Elle hérite de la classe `database` afin de bénéficier de la connexion à la base de données.
 * Le modèle peut être utilisé pour interagir avec la base de données concernant la page d'accueil.
 */
class companyModel extends database
{

    /**
     * companyModel constructor.
     */
    public function __construct() {
        parent::__construct();
    }


    public function getCompanies(): array {
        $stmt = $this->getBdd()->query("
            SELECT company_name, company_description 
            FROM company 
            ORDER BY company_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>
