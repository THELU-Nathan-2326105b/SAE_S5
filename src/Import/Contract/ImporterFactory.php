<?php 
namespace App\Import\Contract;
/**
 * Contrat de factory pour fournir un Importer prêt à l'emploi.
 *
 */
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use App\Import\Contract\Importer; 

class ImporterFactory
{
    private ContainerInterface $locator;


    public function __construct(ContainerInterface $locator) {
        $this->locator=$locator;
    }


    public function create(string $entity, string $format = 'csv'): Importer
    {
        $entity = strtolower(trim($entity));
        $format = strtolower(trim($format));

        if ($entity==='' || $format==='') {
            throw new InvalidArgumentException('format et entity sont obligatoires.');
        }
        else{
            $key = "{$format}.{$entity}";
            if (!$this->locator->has($key)) {
                throw new InvalidArgumentException("Pas d'importeur pour {$key}");
            }
            else{
                $importer = $this->locator->get($key);
                if (!$importer instanceof Importer) {
                    throw new InvalidArgumentException("Le service '{$key}' n'implémente pas ".Importer::class);
                }
                else{
                    return $importer;
                }

                
                
            }
            
        }   
    }
}