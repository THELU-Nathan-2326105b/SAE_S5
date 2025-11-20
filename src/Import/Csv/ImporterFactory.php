<?php 
namespace App\Import\Csv;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use App\Import\Contract\Importer; 
use App\Import\Contract\ImporterFactory  as ImporterFactoryContract;

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
final class ImporterFactory implements ImporterFactoryContract
{
    
    /**
     * Service locator contenant les importeurs disponibles.
    **/
    private ContainerInterface $locator;

    // private const ENTITY="users";
    // default format for the factory (format part of the service key "<format>.<entity>")
    private const FORMAT = 'csv';

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


    /**
     * Normalise une chaîne (trim + lowercase).
    */
    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    /**
     * Construit la clé de service "<format>.<entity>".
    */
    private function buildKey(string $format, string $entity): string
    {
        return sprintf('%s.%s', $format, $entity);
    }


    /**
     * Récupère l’importeur dans le locator ou lève une exception explicite.
     */
    private function getImporterOrFail(string $key): Importer
    {
        if (!$this->locator->has($key)) {
            throw new InvalidArgumentException("Pas d'importeur enregistré pour la clé '{$key}'.");
        }
        else{
            $importer = $this->locator->get($key);
            if (!$importer instanceof Importer) {
                throw new InvalidArgumentException(
                    "Le service '{$key}' n'implémente pas " . Importer::class);
            }
            return $importer;
        }

        
    }

    public function create(string $entity): Importer
    {
        // normalize and validate the entity name
        $entityNormalize = (string)$this->normalize($entity);

        if ($entityNormalize === '') {
            throw new InvalidArgumentException('L’entité à importer est obligatoire.');
        }
        else{
            $key = $this->buildKey(self::FORMAT, $entityNormalize);
            $importer = $this->getImporterOrFail($key);
            return $importer;
        }

    }

}