<?php 
namespace App\Import\Contract;
/**
 * Contrat de factory pour fournir un Importer prêt à l'emploi.
 *
 */
use InvalidArgumentException;
use Psr\Container\ContainerInterface;

class ImporterFactory
{
    private ContainerInterface $locator;


    public function __construct() {}


    public function create(string $entity, string $format = 'csv'): Importer
    {
        $key = strtolower("$format.$entity"); 
        if (!$this->locator->has($key)){
            throw new \InvalidArgumentException("Pas d'importeur pour $key");
        }
        return $this->locator->get($key); 
    }
}