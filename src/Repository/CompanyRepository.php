<?php

namespace App\Repository;

use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Company>
 */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /**
     * Exemple : récupérer toutes les entreprises triées par nom
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.company_name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les entreprises pour un étudiant selon son level et le forum
     * !!! A revoir complétement : A therme mieux vaudrait formaliser la BD pour un fonctionement complet avec doctrine
     * @param int $forumId
     * @param string $studentLevel
     * @return array
     */
    public function findCompaniesForStudent(int $forumId, string $studentLevel): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT c.company_name FROM company c
        JOIN is_present ip ON ip.company_name = c.company_name
        WHERE ip.forum_id = :forumId AND :level = ANY(string_to_array(ip.search_level, \';\'))
        ORDER BY c.company_name ASC';

        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery([
            'forumId' => $forumId,
            'level' => $studentLevel
        ]);

        // on récup les noms d'entreprises
        $companyNames = array_column($resultSet->fetchAllAssociative(), 'company_name');

        if (empty($companyNames)) {
            return [];
        }

        // puis on ajoute les données pour avoir des entites doctrines
        return $this->createQueryBuilder('c')
            ->where('c.company_name IN (:names)')
            ->setParameter('names', $companyNames)
            ->orderBy('c.company_name', 'ASC')
            ->getQuery()
            ->getResult();
    }


}
