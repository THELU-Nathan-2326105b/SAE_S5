<?php

namespace App\Import\Contract;
/**
 * Interface Importer
 *
 * Contrat d'un service d'import qui lit un fichier (ex. CSV)
 * et convertit chaque ligne en entité métier via un Mapper.
 *
 */
interface Importer{

    public function import(string $filePath, string $entity): array;
}