<?php 
namespace App\Import\Contract;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use App\Import\Contract\Importer; 

/**
    * Factory d'importeurs.
    * Retourne un service Importer
    * en fonction :
    *  - de l'entité à importer (ex. "users", "company")
    *  - du format (ex. "csv" par défaut)
    * Récupéré via le ContainerInterface en utilisant
    * une clé de la forme "<format
 */
interface ImporterFactory
{
    /**
     * Retourne un importeur adapté à l'entité et au format demandés.
     *
     * @param string $entity Nom de l'entité à importer 
     *
     * @return Importer Instance d’un service qui implémente Importer.
     *
     * @throws InvalidArgumentException Si 
     *                       $entity ou $format est vide,
     *                       aucun service n’est enregistré pour la clé,
     *                       le service trouvé n’implémente pas Importer.
    **/
    public function create(string $entity): Importer;
}