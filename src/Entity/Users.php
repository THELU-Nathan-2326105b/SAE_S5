<?php

namespace App\Entity;

use App\Repository\UsersRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Users Entity
 * 
 * Représente un utilisateur du système (étudiant, administrateur, etc.).
 * Implémente UserInterface et PasswordAuthenticatedUserInterface pour l'authentification Symfony.
 * 
 * @package App\Entity
 */
#[ORM\Entity(repositoryClass: UsersRepository::class)]
#[ORM\Table(name: 'users')]
class Users implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @var ?int Identifiant unique de l'utilisateur
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'user_id', type: 'integer')]
    private ?int $id = null;

    /**
     * @var ?string Email unique de l'utilisateur (max 320 caractères)
     */
    #[ORM\Column(name: 'user_email', length: 320, unique: true, nullable: true)]
    #[Assert\Email(message: 'Email invalide.')]
    #[Assert\Length(max: 320, maxMessage: '320 caractères max.')]
    #[Assert\Regex(pattern: '/\S/', message: 'L\'email ne peut pas être uniquement des espaces.', match: true)]
    private ?string $user_email = null;
    
    /**
     * @var string Mot de passe hashé (max 60 caractères)
     */
    #[ORM\Column(name: 'user_pwd', length: 60)]
    private string $user_pwd;

    /**
     * @var string Rôle de l'utilisateur (max 10 caractères, ex: 'student', 'admin')
     */
    #[ORM\Column(name: 'user_role', length: 10)]
    private string $user_role;

    /**
     * @var ?bool Indique si c'est la première connexion de l'utilisateur
     */
    #[ORM\Column(name: 'user_firstconnexion', type: 'boolean', options: ['default' => true])]
    private ?bool $user_firstconnexion = true;

    /**
     * @var ?string Prénom de l'utilisateur (max 50 caractères)
     */
    #[ORM\Column(name: 'user_firstname', length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: '50 caractères max.')]
    #[Assert\Regex(pattern: '/\S/', message: 'Le prénom ne peut pas être uniquement des espaces.', match: true)]
    private ?string $user_firstname = null;

    /**
     * @var ?string Nom de famille de l'utilisateur (max 50 caractères)
     */
    #[ORM\Column(name: 'user_lastname', length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: '50 caractères max.')]
    #[Assert\Regex(pattern: '/\S/', message: 'Le nom ne peut pas être uniquement des espaces.', match: true)]
    private ?string $user_lastname = null;

    /**
     * @var ?string Niveau d'études de l'utilisateur (max 2 caractères)
     */
    #[ORM\Column(name: 'user_level', length:2, nullable: true)]
    private ?string $user_level = null;

    /**
     * @var \DateTimeInterface Date de la dernière connexion de l'utilisateur
     */
    #[ORM\Column(name: 'user_lastconnexion', type: 'date')]
    private \DateTimeInterface $user_lastconnexion;

    /**
     * @var ?string URL du curriculum vitae (max 200 caractères)
     */
    #[ORM\Column(name: 'user_url_cv', length: 200, unique: true, nullable: true)]
    private ?string $user_url_cv = null;

    /**
     * @var Collection Rendez-vous associés à cet utilisateur
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Appointment::class)]
    private Collection $appointments;

    /**
     * Constructeur - Initialise la collection de rendez-vous
     */
    public function __construct()
    {
        $this->appointments = new ArrayCollection();
    }

    // --------------------
    // Getters & Setters
    // --------------------

    /**
     * Retourne l'objet utilisateur lui-même
     * 
     * @return object Instance courante
     */
    public function getUser(): object
    {
        return $this;
    }

    /**
     * Récupère l'identifiant unique de l'utilisateur
     * 
     * @return ?int Identifiant ou null si non sauvegardé
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Récupère l'email de l'utilisateur
     * 
     * @return ?string Email de l'utilisateur
     */
    public function getUserEmail(): ?string
    {
        return $this->user_email;
    }

    /**
     * Définit l'email de l'utilisateur
     * 
     * @param ?string $user_email Email de l'utilisateur
     * @return self Instance courante pour le chaînage
     */
    public function setUserEmail(?string $user_email): self
    {
        if ($user_email !== null) {
            $user_email = trim($user_email);
            if ($user_email === '') {
                $user_email = null;
            }
        }
        $this->user_email = $user_email;
        return $this;
    }

    /**
     * Récupère le mot de passe hashé
     * 
     * @return string Mot de passe hashé
     */
    public function getUserPwd(): string
    {
        return $this->user_pwd;
    }

    /**
     * Définit le mot de passe hashé
     * 
     * @param string $user_pwd Mot de passe hashé
     * @return self Instance courante pour le chaînage
     */
    public function setUserPwd(string $user_pwd): self
    {
        $this->user_pwd = $user_pwd;
        return $this;
    }

    /**
     * Récupère le rôle de l'utilisateur
     * 
     * @return string Rôle de l'utilisateur
     */
    public function getUserRole(): string
    {
        return $this->user_role;
    }

    /**
     * Définit le rôle de l'utilisateur
     * 
     * @param string $user_role Rôle de l'utilisateur
     * @return self Instance courante pour le chaînage
     */
    public function setUserRole(string $user_role): self
    {
        $this->user_role = $user_role;
        return $this;
    }

    /**
     * Vérifie si c'est la première connexion de l'utilisateur
     * 
     * @return ?bool true si première connexion, false sinon, null si indéfini
     */
    public function isUserFirstconnexion(): ?bool
    {
        return $this->user_firstconnexion;
    }

    /**
     * Définit le statut de première connexion
     * 
     * @param ?bool $user_firstconnexion true pour première connexion
     * @return self Instance courante pour le chaînage
     */
    public function setUserFirstconnexion(?bool $user_firstconnexion): self
    {
        $this->user_firstconnexion = $user_firstconnexion;
        return $this;
    }

    /**
     * Récupère le prénom de l'utilisateur
     * 
     * @return ?string Prénom de l'utilisateur
     */
    public function getUserFirstname(): ?string
    {
        return $this->user_firstname;
    }

    /**
     * Définit le prénom de l'utilisateur
     * 
     * @param ?string $user_firstname Prénom de l'utilisateur
     * @return self Instance courante pour le chaînage
     */
    public function setUserFirstname(?string $user_firstname): self
    {
        if ($user_firstname !== null) {
            $user_firstname = trim($user_firstname);
            if ($user_firstname === '') {
                $user_firstname = null;
            }
        }
        $this->user_firstname = $user_firstname;
        return $this;
    }

    /**
     * Récupère le nom de famille de l'utilisateur
     * 
     * @return ?string Nom de famille de l'utilisateur
     */
    public function getUserLastname(): ?string
    {
        return $this->user_lastname;
    }

    /**
     * Définit le nom de famille de l'utilisateur
     * 
     * @param ?string $user_lastname Nom de famille de l'utilisateur
     * @return self Instance courante pour le chaînage
     */
    public function setUserLastname(?string $user_lastname): self
    {
        if ($user_lastname !== null) {
            $user_lastname = trim($user_lastname);
            if ($user_lastname === '') {
                $user_lastname = null;
            }
        }
        $this->user_lastname = $user_lastname;
        return $this;
    }

    /**
     * Récupère le niveau d'études de l'utilisateur
     * 
     * @return ?string Niveau d'études
     */
    public function getUserLevel(): ?string
    {
        return $this->user_level;
    }

    /**
     * Définit le niveau d'études de l'utilisateur
     * 
     * @param ?string $user_level Niveau d'études
     * @return self Instance courante pour le chaînage
     */
    public function setUserLevel(?string $user_level): self
    {
        $this->user_level = $user_level;
        return $this;
    }

    /**
     * Récupère la date de dernière connexion
     * 
     * @return \DateTimeInterface Date de la dernière connexion
     */
    public function getUserLastconnexion(): \DateTimeInterface
    {
        return $this->user_lastconnexion;
    }

    /**
     * Définit la date de dernière connexion
     * 
     * @param \DateTimeInterface $user_lastconnexion Date de dernière connexion
     * @return self Instance courante pour le chaînage
     */
    public function setUserLastconnexion(\DateTimeInterface $user_lastconnexion): self
    {
        $this->user_lastconnexion = $user_lastconnexion;
        return $this;
    }

    /**
     * Récupère l'URL du curriculum vitae
     * 
     * @return ?string URL du CV
     */
    public function getUserUrlCv(): ?string
    {
        return $this->user_url_cv;
    }

    /**
     * Définit l'URL du curriculum vitae
     * 
     * @param ?string $user_url_cv URL du CV
     * @return self Instance courante pour le chaînage
     */
    public function setUserUrlCv(?string $user_url_cv): self
    {
        $this->user_url_cv = $user_url_cv;
        return $this;
    }

    // --------------------
    // Implémentation de UserInterface
    // --------------------

    /**
     * Retourne l'identifiant unique de l'utilisateur (email)
     * Requis par UserInterface
     * 
     * @return string Identifiant unique (email)
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->user_email;
    }

    /**
     * Retourne les rôles de l'utilisateur
     * Convertit user_role (string) en tableau de rôles Symfony
     * Requis par UserInterface
     * 
     * @return array Tableau des rôles avec le préfixe ROLE_
     */
    public function getRoles(): array
    {
        $role = $this->user_role;

        if (!str_starts_with($role, 'ROLE_')) {
            $role = 'ROLE_' . strtoupper($role);
        }

        return array_unique([$role, 'ROLE_USER']);
    }

    /**
     * Efface les données sensibles temporaires
     * Requis par UserInterface
     * 
     * @return void
     */
    public function eraseCredentials(): void
    {
        // Si vous stockez un plainPassword temporaire, nettoyez-le ici
        // $this->plainPassword = null;
    }

    /**
     * Retourne le mot de passe hashé
     * Requis par PasswordAuthenticatedUserInterface
     * 
     * @return ?string Mot de passe hashé
     */
    public function getPassword(): ?string
    {
        return $this->user_pwd;
    }
}
