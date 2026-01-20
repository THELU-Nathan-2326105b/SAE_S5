<?php

namespace App\Repository;

use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * UsersRepository
 * 
 * Repository pour gérer les requêtes sur l'entité Users.
 * Fournit des méthodes pour rechercher les utilisateurs selon différents critères.
 * 
 * @extends ServiceEntityRepository<Users>
 * @package App\Repository
 */
class UsersRepository extends ServiceEntityRepository
{
    /**
     * Constructeur du repository
     * 
     * @param ManagerRegistry $registry Registre du gestionnaire d'entités
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Users::class);
    }

    /**
     * Cherche un utilisateur par son email
     * 
     * @param string $email Email de l'utilisateur
     * @return ?Users L'utilisateur trouvé ou null
     */
    public function findOneByEmail(string $email): ?Users
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.user_email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si un email existe déjà
     * 
     * @param string $email Email à vérifier
     * @return bool true si l'email existe, false sinon
     */
    public function existsByEmail(string $email): bool
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.user_email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}