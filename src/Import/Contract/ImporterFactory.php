<?php 
namespace App\Import\Contract;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use App\Import\Contract\Importer; 

/**
 * ImporterFactory
 * 
 * Factory d'importeurs permettant d'obtenir un service Importer
 * en fonction de l'entité à importer et du format de fichier.
 * Utilise un service locator pour récupérer les importeurs enregistrés
 * avec une clé de la forme "<format>.<entity>".
 *
 * @package App\Import\Contract
 */
class ImporterFactory
{
    /**
     * Service locator contenant les importeurs disponibles
     * 
     * @var ContainerInterface
     */
    private ContainerInterface $locator;

    /**
     * Constructeur de la factory
     * 
     * @param ContainerInterface $locator Conteneur qui fournit les services Importer
     */
    public function __construct(ContainerInterface $locator) {
        $this->locator=$locator;
    }

    /**
     * Retourne un importeur adapté à l'entité et au format demandés
     *
     * @param string $entity Nom de l'entité à importer (ex: "users", "company")
     * @param string $format Format du fichier à importer (défaut: "csv")
     *
     * @return Importer Instance d'un service qui implémente Importer
     *
     * @throws InvalidArgumentException Si $entity ou $format est vide,
     *                                  si aucun service n'est enregistré pour la clé,
     *                                  ou si le service trouvé n'implémente pas Importer
     */
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