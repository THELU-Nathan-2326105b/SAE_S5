<?php

namespace App\Entity;

use App\Repository\ForumRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumRepository::class)]
#[ORM\Table(name: "forum")]
class Forum
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "forum_id", type: "smallint")]
    private ?int $id = null;

    #[ORM\Column(name: "forum_date", type: "date")]
    private \DateTimeInterface $date;

    #[ORM\Column(name: "forum_address", type: "string", length: 200)]
    private string $address;

    #[ORM\Column(name: "forum_name", type: "string", length: 50)]
    private string $name;

    // -------- Getters & Setters ----------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}