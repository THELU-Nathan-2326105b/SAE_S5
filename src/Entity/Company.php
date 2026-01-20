<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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
    #[ORM\Column(name: 'company_name', type: 'string', length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Le nom est requis.')]
    #[Assert\Length(min: 2, max: 100, minMessage: '2 caractères min.', maxMessage: '100 caractères max.')]
    #[Assert\Regex(pattern: '/\S/', message: 'Le nom doit contenir au moins un caractère non blanc.')]
    private string $company_name = '';

    #[ORM\Column(name: 'company_description', type: 'string', length: 300)]
    #[Assert\NotBlank(message: 'La description est requise.')]
    #[Assert\Length(min: 2, max: 300, minMessage: '2 caractères min.', maxMessage: '300 caractères max.')]
    #[Assert\Regex(pattern: '/\S/', message: 'La description ne peut pas être uniquement des espaces.')]
    private string $company_description = '';

    #[ORM\Column(name: 'company_logo', type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Le logo est requis.')]
    #[Assert\Length(min: 2, max: 100, minMessage: '2 caractères min.', maxMessage: '100 caractères max.')]
    #[Assert\Regex(pattern: '/\S/', message: 'Le logo ne peut pas être uniquement des espaces.')]
    private string $company_logo = '';

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
    public function setCompanyName(?string $company_name): self
    {
        if ($company_name === null) {
            $this->company_name = '';
        } else {
            $this->company_name = trim($company_name);
        }
        return $this;
    }

    /**
     * Récupère la description de l'entreprise
     * 
     * @return string Description de l'entreprise
     */
    public function getCompanyDescription(): string
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
        if ($company_description === null) {
            $this->company_description = '';
        } else {
            $company_description = trim($company_description);
            if ($company_description === '') {
                $company_description = '';
            }
        }
        $this->company_description = $company_description;
        return $this;
    }

    /**
     * Récupère l'URL du logo de l'entreprise
     * 
     * @return string URL du logo
     */
    public function getCompanyLogo(): string
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
        if ($company_logo === null) {
            $this->company_logo = '';
        } else {
            $company_logo = trim($company_logo);
            if ($company_logo === '') {
                $company_logo = '';
            }
        }
        $this->company_logo = $company_logo;
        return $this;
    }
}
