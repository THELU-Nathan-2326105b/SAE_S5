<?php

namespace App\Mapper\Company;

use App\Entity\Company;
use InvalidArgumentException;
use App\Mapper\Contract\Mapper as MapperContract;

/**
 * Company Mapper
 *
 * Mappe une ligne CSV (tableau associatif) vers l'entité `Company` et inversement.
 */
final class Mapper implements MapperContract
{
    private function isValidEntity(object $entity): bool
    {
        return $entity instanceof Company;
    }

    /**
     * Convertit une ligne associative en entité Company.
     * Attendu des clés CSV : `company_name`, `company_description`, `company_logo`.
     */
    public function fromRow(array $row): object
    {
        $company = new Company();

        // Les entêtes sont normalisées en minuscules par l'importeur
        $name = isset($row['company_name']) ? trim((string) $row['company_name']) : '';
        if ($name === '') {
            throw new \RuntimeException("Le nom d'une des entreprises est vide.");
        }
        $company->setCompanyName($name);

        $company->setCompanyDescription($row['company_description'] ?? null);
        $company->setCompanyLogo($row['company_logo'] ?? null);

        return $company;
    }

    /**
     * Convertit une entité Company en ligne associative (pour export CSV).
     * @throws InvalidArgumentException si l'objet n'est pas une Company.
     */
    public function toRow(object $entity): array
    {
        if (!$this->isValidEntity($entity)) {
            throw new InvalidArgumentException('CompanyMapper attend une instance de App\\Entity\\Company.');
        }

        /** @var Company $entity */
        return [
            'company_name' => $entity->getCompanyName(),
            'company_description' => $entity->getCompanyDescription(),
            'company_logo' => $entity->getCompanyLogo(),
        ];
    }
}

