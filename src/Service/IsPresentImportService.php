<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Service pour gérer l'import des données is_present et company depuis CSV
 */
class IsPresentImportService
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Insère des données dans company et is_present à partir d'un tableau de lignes CSV
     *
     * @param array $rows Tableau de lignes CSV
     * @return int Nombre de lignes insérées
     */
    public function importFromCsv(array $rows): int
    {
        $inserted = 0;

        foreach ($rows as $row) {
            // 1. Validation des champs obligatoires
            if (empty($row['forum_id']) || empty($row['company_name'])) {
                throw new \RuntimeException("forum_id ou company_name manquant dans le CSV");
            }

            if (empty($row['start_time']) || empty($row['end_time'])) {
                throw new \RuntimeException("start_time ou end_time manquant pour {$row['company_name']}");
            }

            $companyName = trim($row['company_name']);


            // 2. INSERTION DE L'ENTREPRISE
            $sqlCompany = "INSERT INTO company (company_name, company_description, company_logo)
                           VALUES (:name, :desc, :logo)
                           ON CONFLICT (company_name) DO UPDATE SET
                               company_description = EXCLUDED.company_description,
                               company_logo = EXCLUDED.company_logo";

            $this->connection->executeStatement($sqlCompany,[
                'name' => $companyName,
                'desc' => trim($row['company_description'] ?? 'Aucune description'),
                'logo' => trim($row['company_logo'] ?? '/uploads/logos/default.png')
            ]);

            // 3. Valeurs par défaut pour la présence
            $searchType = trim($row['search_type'] ?? 'internship;alternance');
            $searchLevel = trim($row['search_level'] ?? 'B1;B2;B3;M1;M2');

            // 4. Validation search_type
            if (!in_array($searchType, ['internship', 'alternance', 'internship;alternance'])) {
                throw new \RuntimeException("search_type invalide pour {$companyName}: {$searchType}");
            }

            // 5. INSERTION DE LA PRÉSENCE
            $sqlIsPresent = "INSERT INTO is_present (forum_id, company_name, start_time, end_time, search_type, search_level)
                             VALUES (:forum_id, :company_name, :start_time, :end_time, :search_type, :search_level)
                             ON CONFLICT (forum_id, company_name) DO UPDATE SET
                                 start_time = EXCLUDED.start_time,
                                 end_time = EXCLUDED.end_time,
                                 search_type = EXCLUDED.search_type,
                                 search_level = EXCLUDED.search_level";

            $this->connection->executeStatement($sqlIsPresent, [
                'forum_id' => (int) $row['forum_id'],
                'company_name' => $companyName,
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'search_type' => $searchType,
                'search_level' => $searchLevel,
            ]);

            $inserted++;
        }

        return $inserted;
    }
}
