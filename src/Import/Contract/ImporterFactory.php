<?php 
namespace App\Import\Contract;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use App\Import\Contract\Importer; 

/**
 * Factory d'importeurs.
 *
 * Retourne un service Importer
 * en fonction :
 *  - de l'entité à importer (ex. "users", "company")
 *  - du format (ex. "csv" par défaut)
 *
 * Récupéré via le ContainerInterface en utilisant
 * une clé de la forme "<format>.<entity>" 
 */
class ImporterFactory
{
    
    /**
     * Service locator contenant les importeurs disponibles.
    **/
    private ContainerInterface $locator;

    /**
     * @param ContainerInterface $locator Conteneur qui fournit les services Importer
    */
    public function __construct(ContainerInterface $locator) {
        $this->locator=$locator;
    }

    /**
     * Retourne un importeur adapté à l'entité et au format demandés.
     *
     * @param string $entity Nom de l'entité à importer 
     * @param string $format Format du fichier à importer.
     *
     * @return Importer Instance d’un service qui implémente Importer.
     *
     * @throws InvalidArgumentException Si 
     *                       $entity ou $format est vide,
     *                       aucun service n’est enregistré pour la clé,
     *                       le service trouvé n’implémente pas Importer.
    **/
    public function create(string $entity, string $format = 'csv'): Importer
    {
        $entity = strtolower(trim($entity));
        $format = strtolower(trim($format));

        if (empty($entity) || empty($format)) {
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
                return $importer;   
            }
            
        }   
    }
}