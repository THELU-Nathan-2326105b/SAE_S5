<?php
namespace App\Mapper\Contract;
/**
 * Mapper
 *
 * Interface définissant le contrat pour la conversion bidirectionnelle
 * entre des lignes de données (tableaux associatifs) et des entités métiers.
 * Utilisé principalement pour l'import/export CSV.
 * 
 * @package App\Mapper\Contract
 */
interface Mapper{
    /**
     * Transforme une ligne de données en entité métier
     * 
     * @param array<string,mixed> $row Tableau associatif contenant les données
     * 
     * @return object Entité métier hydratée à partir des données
     */
    public function fromRow(array $row):object;


    /**
     * Transforme une entité en ligne associative
     *
     * @param object $entity Entité métier à convertir
     * 
     * @return array<string,mixed> Tableau associatif prêt à être écrit (ex: CSV)
     */
    public function toRow(object $entity): array;
}