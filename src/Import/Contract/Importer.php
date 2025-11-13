<?php

namespace App\Import\Contract;

/**
 * Interface Importer
 *
 * Représente un service capable d'importer des données et de les convertir
 * en entités métiers à l'aide d'un Mapper.
 */
interface Importer{
    /**
     * Importe les données d'un fichier et retourne les entités créées.
     *
     * @param string $filePath Chemin complet vers le fichier 
     * @param string $entity   Nom de l'entité métier à créer 
     *
     * @return array           Tableau d'objets métiers créés à partir du fichier.
     */
    public function import(string $filePath, string $entity): array;
}