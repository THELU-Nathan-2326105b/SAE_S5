<?php

namespace App\Entity;

use App\Repository\UsersRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UsersRepository::class)]
#[ORM\Table(name: 'users')]
class Users implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'user_id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'user_email', length: 320, unique: true, nullable: true)]
    private ?string $user_email = null;

    #[ORM\Column(name: 'user_pwd', length: 60)]
    private string $user_pwd;

    #[ORM\Column(name: 'user_role', length: 10)]
    private string $user_role;

    #[ORM\Column(name: 'user_firstconnexion', type: 'boolean', options: ['default' => true])]
    private ?bool $user_firstconnexion = true;

    #[ORM\Column(name: 'user_firstname', length: 50, nullable: true)]
    private ?string $user_firstname = null;

    #[ORM\Column(name: 'user_lastname', length: 50, nullable: true)]
    private ?string $user_lastname = null;

    #[ORM\Column(name: 'user_level', length:2, nullable: true)]
    private ?string $user_level = null;

    #[ORM\Column(name: 'user_lastconnexion', type: 'date')]
    private \DateTimeInterface $user_lastconnexion;

    #[ORM\Column(name: 'user_url_cv', length: 200, unique: true, nullable: true)]
    private ?string $user_url_cv = null;


    // Relations (décommenter si Appointment existe)
    /*
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Appointment::class)]
    private Collection $appointments;

    public function __construct()
    {
        $this->appointments = new ArrayCollection();
    }
    */

    // --------------------
    // Getters & Setters
    // --------------------

    public function getUser(): object
    {
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserEmail(): ?string
    {
        return $this->user_email;
    }

    public function setUserEmail(?string $user_email): self
    {
        $this->user_email = $user_email;
        return $this;
    }

    public function getUserPwd(): string
    {
        return $this->user_pwd;
    }

    public function setUserPwd(string $user_pwd): self
    {
        $this->user_pwd = $user_pwd;
        return $this;
    }

    public function getUserRole(): string
    {
        return $this->user_role;
    }

    public function setUserRole(string $user_role): self
    {
        $this->user_role = $user_role;
        return $this;
    }

    public function isUserFirstconnexion(): ?bool
    {
        return $this->user_firstconnexion;
    }

    public function setUserFirstconnexion(?bool $user_firstconnexion): self
    {
        $this->user_firstconnexion = $user_firstconnexion;
        return $this;
    }

    public function getUserFirstname(): ?string
    {
        return $this->user_firstname;
    }

    public function setUserFirstname(?string $user_firstname): self
    {
        $this->user_firstname = $user_firstname;
        return $this;
    }

    public function getUserLastname(): ?string
    {
        return $this->user_lastname;
    }

    public function setUserLastname(?string $user_lastname): self
    {
        $this->user_lastname = $user_lastname;
        return $this;
    }

    public function getUserLevel(): ?string
    {
        return $this->user_level;
    }

    public function setUserLevel(?string $user_level): self
    {
        $this->user_level = $user_level;
        return $this;
    }

    public function getUserLastconnexion(): \DateTimeInterface
    {
        return $this->user_lastconnexion;
    }

    public function setUserLastconnexion(\DateTimeInterface $user_lastconnexion): self
    {
        $this->user_lastconnexion = $user_lastconnexion;
        return $this;
    }


    // --------------------
    // Méthodes UserInterface
    // --------------------

    /**
     * Retourne l'identifiant unique de l'utilisateur (email)
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->user_email;
    }

    /**
     * Retourne les rôles de l'utilisateur
     * Convertit user_role (string) en tableau de rôles Symfony
     */
    public function getRoles(): array
    {
        // Convertit votre champ user_role en format Symfony
        $role = $this->user_role;
        
        // Ajoute le préfixe ROLE_ si nécessaire
        if (!str_starts_with($role, 'ROLE_')) {
            $role = 'ROLE_' . strtoupper($role);
        }
        
        // Garantit que chaque utilisateur a au moins ROLE_USER
        return array_unique([$role, 'ROLE_USER']);
    }

    /**
     * Efface les données sensibles temporaires
     */
    public function eraseCredentials(): void
    {
        // Si vous stockez un plainPassword temporaire, nettoyez-le ici
        // $this->plainPassword = null;
    }
    */

    // --------------------
    // Méthodes UserInterface
    // --------------------

    /**
     * Retourne l'identifiant unique de l'utilisateur (email)
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->user_email;
    }

    /**
     * Retourne les rôles de l'utilisateur
     * Convertit user_role (string) en tableau de rôles Symfony
     */
    public function getRoles(): array
    {
        // Convertit votre champ user_role en format Symfony
        $role = $this->user_role;

        // Ajoute le préfixe ROLE_ si nécessaire
        if (!str_starts_with($role, 'ROLE_')) {
            $role = 'ROLE_' . strtoupper($role);
        }

        // Garantit que chaque utilisateur a au moins ROLE_USER
        return array_unique([$role, 'ROLE_USER']);
    }

    /**
     * Efface les données sensibles temporaires
     */
    public function eraseCredentials(): void
    {
        // Si vous stockez un plainPassword temporaire, nettoyez-le ici
        // $this->plainPassword = null;
    }

    /**
     * Retourne le mot de passe hashé
     * Requis par PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->user_pwd;
    }

    public function getUserUrlCv(): ?string
    {
        return $this->user_url_cv;
    }

    public function setUserUrlCv(?string $user_url_cv): self
    {
        $this->user_url_cv = $user_url_cv;
        return $this;
    }

}
