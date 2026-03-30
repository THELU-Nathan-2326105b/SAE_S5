<?php

namespace App\Repository;

use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * CompanyRepository
 *
 * Repository pour gérer les requêtes sur l'entité Company.
 * Fournit des méthodes pour rechercher les entreprises selon différents critères.
 *
 * @extends ServiceEntityRepository<Company>
 * @package App\Repository
 */
class CompanyRepository extends ServiceEntityRepository
{
    /**
     * Constructeur du repository
     *
     * @param ManagerRegistry $registry Registre du gestionnaire d'entités
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /**
     * Récupère toutes les entreprises triées par nom
     *
     * @return array Tableau de toutes les entreprises par ordre alphabétique
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.company_name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les entreprises correspondant au profil d'un étudiant pour un forum
     * Filtre selon le niveau de l'étudiant et les critères de recherche des entreprises
     *
     * Note : Cette méthode utilise une requête SQL brute car les critères de recherche
     * sont stockés dans une table non mappée par Doctrine (is_present).
     * À terme, il faudrait formaliser la base de données pour une meilleure intégration.
     *
     * @param int $forumId Identifiant du forum
     * @param string $studentLevel Niveau de l'étudiant
     * @return array Tableau des entreprises correspondant aux critères
     */
    public function findCompaniesForStudent(int $forumId, string $studentLevel, string $studentRole): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT c.company_name FROM company c
        JOIN is_present ip ON ip.company_name = c.company_name
        WHERE ip.forum_id = :forumId
        AND :level = ANY(string_to_array(ip.search_level, \';\'))
        AND :role  = ANY(string_to_array(ip.search_type, \';\'))
        ORDER BY c.company_name ASC';

        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery([
            'forumId' => $forumId,
            'level' => $studentLevel,
            'role' => $studentRole
        ]);

        // Récupère les noms d'entreprises
        $companyNames = array_column($resultSet->fetchAllAssociative(), 'company_name');


        if (empty($companyNames)) {
            return [];
        }

        // Récupère les entités Doctrine complètes
        return $this->createQueryBuilder('c')
            ->where('c.company_name IN (:names)')
            ->setParameter('names', $companyNames)
            ->orderBy('c.company_name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Dans votre CompanyRepository
    public function existsByName(string $name): bool
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.company_name)')
            ->where('c.company_name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
