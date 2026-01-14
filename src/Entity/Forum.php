<?php

namespace App\Entity;

use App\Repository\ForumRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Forum Entity
 * 
 * Représente un forum (salon professionnel) où les entreprises rencontrent les étudiants.
 * Contient les informations générales du forum (date, lieu, nom).
 * 
 * @package App\Entity
 */
#[ORM\Entity(repositoryClass: ForumRepository::class)]
#[ORM\Table(name: "forum")]
class Forum
{
    /**
     * @var ?int Identifiant unique du forum
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "forum_id", type: "smallint")]
    private ?int $id = null;

    /**
     * @var \DateTimeInterface Date du forum
     */
    #[ORM\Column(name: "forum_date", type: "date")]
    private \DateTimeInterface $date;

    /**
     * @var string Adresse du lieu du forum (max 200 caractères)
     */
    #[ORM\Column(name: "forum_address", type: "string", length: 200)]
    private string $address;

    /**
     * @var string Nom du forum (max 50 caractères)
     */
    #[ORM\Column(name: "forum_name", type: "string", length: 50)]
    private string $name;

    // -------- Getters & Setters ----------

    /**
     * Récupère l'identifiant du forum
     * 
     * @return ?int Identifiant unique ou null si non sauvegardé
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Récupère la date du forum
     * 
     * @return \DateTimeInterface Date du forum
     */
    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    /**
     * Définit la date du forum
     * 
     * @param \DateTimeInterface $date Date du forum
     * @return self Instance courante pour le chaînage de méthodes
     */
    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    /**
     * Récupère l'adresse du forum
     * 
     * @return string Adresse du lieu du forum
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * Définit l'adresse du forum
     * 
     * @param string $address Adresse du lieu du forum
     * @return self Instance courante pour le chaînage de méthodes
     */
    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    /**
     * Récupère le nom du forum
     * 
     * @return string Nom du forum
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Définit le nom du forum
     * 
     * @param string $name Nom du forum
     * @return self Instance courante pour le chaînage de méthodes
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}