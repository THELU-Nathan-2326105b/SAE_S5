<?php

namespace App\Repository;

use App\Entity\Forum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * ForumRepository
 * 
 * Repository pour gérer les requêtes sur l'entité Forum.
 * Fournit des méthodes pour rechercher les forums selon différents critères.
 * 
 * @extends ServiceEntityRepository<Forum>
 * @package App\Repository
 */
class ForumRepository extends ServiceEntityRepository
{
    /**
     * Constructeur du repository
     * 
     * @param ManagerRegistry $registry Registre du gestionnaire d'entités
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forum::class);
    }

    /**
     * Récupère tous les forums triés par date
     * Classe les forums du plus ancien au plus récent
     * 
     * @return array Tableau de tous les forums par ordre chronologique
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
