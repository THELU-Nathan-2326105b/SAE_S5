<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: 'company')]
class Company
{
    #[ORM\Id]
    #[ORM\Column(name: 'company_name', type: 'string', length: 100)]
    private string $company_name;

    #[ORM\Column(name: 'company_description', type: 'string', length: 300, nullable: true)]
    private ?string $company_description = null;

    #[ORM\Column(name: 'company_logo', type: 'string', length: 100, nullable: true)]
    private ?string $company_logo = null;

    // --- Getters & Setters ---

    public function getCompanyName(): string
    {
        return $this->company_name;
    }

    public function setCompanyName(string $company_name): self
    {
        $this->company_name = $company_name;
        return $this;
    }

    public function getCompanyDescription(): ?string
    {
        return $this->company_description;
    }

    public function setCompanyDescription(?string $company_description): self
    {
        $this->company_description = $company_description;
        return $this;
    }

    public function getCompanyLogo(): ?string
    {
        return $this->company_logo;
    }

    public function setCompanyLogo(?string $company_logo): self
    {
        $this->company_logo = $company_logo;
        return $this;
    }
}
