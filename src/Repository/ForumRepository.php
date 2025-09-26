<?php

namespace App\Repository;

use App\Entity\Forum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Forum>
 */
class ForumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forum::class);
    }

    /**
     * Exemple : récupérer les forums triés par date
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
