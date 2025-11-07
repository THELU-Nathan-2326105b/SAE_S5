<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class Users
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'user_id', type: 'integer')]
    private int $user_id;

    #[ORM\Column(name: 'user_email', length: 320, unique: true, nullable: true)]
    private ?string $user_email = null;

    #[ORM\Column(name: 'user_pwd', length: 60)]
    private string $user_pwd;

    #[ORM\Column(name: 'user_token_resetpwd', length: 100, nullable: true)]
    private ?string $user_token_resetpwd = null;

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

    #[ORM\Column(name: 'user_lastconnexion', type: 'datetime')]
    private \DateTimeInterface $user_lastconnexion;

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

    public function getUserId(): int
    {
        return $this->user_id;
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

    public function getUserTokenResetpwd(): ?string
    {
        return $this->user_token_resetpwd;
    }

    public function setUserTokenResetpwd(?string $user_token_resetpwd): self
    {
        $this->user_token_resetpwd = $user_token_resetpwd;
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

    // Relations Appointments (décommenter si nécessaire)
    /*
    public function getAppointments(): Collection
    {
        return $this->appointments;
    }

    public function addAppointment(Appointment $appointment): self
    {
        if (!$this->appointments->contains($appointment)) {
            $this->appointments->add($appointment);
            $appointment->setUser($this);
        }
        return $this;
    }

    public function removeAppointment(Appointment $appointment): self
    {
        if ($this->appointments->removeElement($appointment)) {
            if ($appointment->getUser() === $this) {
                $appointment->setUser(null);
            }
        }
        return $this;
    }
    */
}