<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Company Entity
 * 
 * Représente une entreprise participante aux forums.
 * Stocke les informations de base (nom, description, logo).
 * 
 * @package App\Entity
 */
#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: 'company')]
class Company
{
    /**
     * @var string Nom unique de l'entreprise (clé primaire, max 100 caractères)
     */
    #[ORM\Id]
    #[ORM\Column(name: 'company_name', type: 'string', length: 100)]
    private string $company_name;

    /**
     * @var ?string Description de l'entreprise (max 300 caractères)
     */
    #[ORM\Column(name: 'company_description', type: 'string', length: 300, nullable: true)]
    private ?string $company_description = null;

    /**
     * @var ?string URL du logo de l'entreprise (max 100 caractères)
     */
    #[ORM\Column(name: 'company_logo', type: 'string', length: 100, nullable: true)]
    private ?string $company_logo = null;

    // --------------------
    // Getters & Setters
    // --------------------

    /**
     * Récupère le nom de l'entreprise
     * 
     * @return string Nom unique de l'entreprise
     */
    public function getCompanyName(): string
    {
        return $this->company_name;
    }

    /**
     * Définit le nom de l'entreprise
     * 
     * @param string $company_name Nom unique de l'entreprise
     * @return self Instance courante pour le chaînage
     */
    public function setCompanyName(string $company_name): self
    {
        $this->company_name = $company_name;
        return $this;
    }

    /**
     * Récupère la description de l'entreprise
     * 
     * @return ?string Description de l'entreprise
     */
    public function getCompanyDescription(): ?string
    {
        return $this->company_description;
    }

    /**
     * Définit la description de l'entreprise
     * 
     * @param ?string $company_description Description de l'entreprise
     * @return self Instance courante pour le chaînage
     */
    public function setCompanyDescription(?string $company_description): self
    {
        $this->company_description = $company_description;
        return $this;
    }

    /**
     * Récupère l'URL du logo de l'entreprise
     * 
     * @return ?string URL du logo
     */
    public function getCompanyLogo(): ?string
    {
        return $this->company_logo;
    }

    /**
     * Définit l'URL du logo de l'entreprise
     * 
     * @param ?string $company_logo URL du logo
     * @return self Instance courante pour le chaînage
     */
    public function setCompanyLogo(?string $company_logo): self
    {
        $this->company_logo = $company_logo;
        return $this;
    }
}
