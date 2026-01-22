<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Service pour gérer l'import des données is_present depuis CSV
 */
class IsPresentImportService
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Insère des données dans is_present à partir d'un tableau de lignes CSV
     * 
     * @param array $rows Tableau de lignes avec : forum_id, company_name, start_time, end_time, search_type, search_level
     * @return int Nombre de lignes insérées
     */
    public function importFromCsv(array $rows): int
    {
        $inserted = 0;
        
        foreach ($rows as $row) {
            // Validation des champs obligatoires
            if (empty($row['forum_id']) || empty($row['company_name'])) {
                throw new \RuntimeException("forum_id ou company_name manquant dans le CSV");
            }
            
            if (empty($row['start_time']) || empty($row['end_time'])) {
                throw new \RuntimeException("start_time ou end_time manquant pour {$row['company_name']}");
            }
            
            // Valeurs par défaut
            $searchType = trim($row['search_type'] ?? 'internship;alternance');
            $searchLevel = trim($row['search_level'] ?? 'B1;B2;B3;M1;M2');
            
            // Validation search_type
            if (!in_array($searchType, ['internship', 'alternance', 'internship;alternance'])) {
                throw new \RuntimeException("search_type invalide pour {$row['company_name']}: {$searchType}");
            }
            
            // Insertion SQL directe (ON CONFLICT pour éviter les doublons)
            $sql = "INSERT INTO is_present (forum_id, company_name, start_time, end_time, search_type, search_level) 
                    VALUES (:forum_id, :company_name, :start_time, :end_time, :search_type, :search_level)
                    ON CONFLICT (forum_id, company_name) DO UPDATE SET
                        start_time = EXCLUDED.start_time,
                        end_time = EXCLUDED.end_time,
                        search_type = EXCLUDED.search_type,
                        search_level = EXCLUDED.search_level";
            
            $this->connection->executeStatement($sql, [
                'forum_id' => (int) $row['forum_id'],
                'company_name' => trim($row['company_name']),
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