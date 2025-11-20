<?php
namespace App\Mapper\Users;
use App\Mapper\Contract\Mapper;
use App\Mapper\Contract\MapperFactory as MapperFactoryContract;
use Psr\Container\ContainerInterface;
use InvalidArgumentException;

class MapperFactory implements MapperFactoryContract
{
    public function __construct(
        private ContainerInterface $locator
    ) {}

    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    public function create(string $entity): Mapper
    {
        $normalized = $this->normalize($entity);
        if ($normalized === '') {
            throw new InvalidArgumentException("L’entité à mapper est obligatoire.");
        }
        else{
            $key = $normalized;
            if (!$this->locator->has($key)){
                throw new InvalidArgumentException("Aucun mapper enregistré pour l’entité « {$entity} » (clé : {$key}).");
            }
            else{
                $mapper = $this->locator->get($key);
                if (!$mapper instanceof Mapper) {
                    throw new InvalidArgumentException(
                        "Le service « {$key} » n'implémente pas " . Mapper::class
                    );
                }

                return $mapper;
            }
        }

            

        
    }
}
