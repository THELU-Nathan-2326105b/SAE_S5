<?php

namespace App\Import\Contract;

/**
 * Importer
 *
 * Interface définissant le contrat pour les services d'import de données.
 * Représente un service capable d'importer des données depuis un fichier
 * et de les convertir en entités métiers à l'aide d'un Mapper.
 * 
 * @package App\Import\Contract
 */
interface Importer{
    /**
     * Importe les données d'un fichier et retourne les entités créées
     *
     * @param string $filePath Chemin complet vers le fichier à importer
     * @param string $entity   Nom de l'entité métier à créer (ex: "users", "company")
     *
     * @return array<object> Tableau d'objets métiers créés à partir du fichier
     */
    public function import(string $filePath, string $entity): array;
}