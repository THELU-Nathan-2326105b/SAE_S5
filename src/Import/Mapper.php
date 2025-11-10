<?php
/**
 * Interface Mapper
 *
 * Convertion d'une ligne de données et une entité métier.
 *
**/
interface Mapper{
    /**
     * Transforme une ligne données en entité.
     * @param array $row Tableau associatif .
     * @return object Entité métier hydratée à partir des données.
     *
    **/
    public function fromRow(array $row):Object;


    /**
     * Transforme une entité en ligne associative.
     *
     * @param object $entity Entité métier à convertir.
     * @return array Tableau associatif prêt à être écrit (ex. CSV).
     *
     */
    public function toRow(object $entity): array;
}