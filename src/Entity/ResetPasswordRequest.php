<?php

namespace App\Entity;

use App\Repository\ResetPasswordRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestTrait;

/**
 * ResetPasswordRequest Entity
 * 
 * Représente une demande de réinitialisation de mot de passe.
 * Utilise le trait ResetPasswordRequestTrait du bundle SymfonyCasts.
 * 
 * @package App\Entity
 */
#[ORM\Entity(repositoryClass: ResetPasswordRequestRepository::class)]
class ResetPasswordRequest implements ResetPasswordRequestInterface
{
    use ResetPasswordRequestTrait;

    /**
     * @var ?int Identifiant unique de la demande
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * @var ?Users Utilisateur ayant demandé la réinitialisation
     */
    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false)]
    private ?Users $user = null;

    /**
     * Constructeur de la demande de réinitialisation de mot de passe
     * 
     * @param Users $user Utilisateur demandant la réinitialisation
     * @param \DateTimeInterface $expiresAt Date/heure d'expiration du token
     * @param string $selector Sélecteur du token
     * @param string $hashedToken Token hashé
     */
    public function __construct(Users $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken)
    {
        $this->user = $user;
        $this->initialize($expiresAt, $selector, $hashedToken);
    }

    /**
     * Récupère l'identifiant unique de la demande
     * 
     * @return ?int Identifiant ou null si non sauvegardé
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Récupère l'utilisateur ayant demandé la réinitialisation
     * 
     * @return Users L'utilisateur
     */
    public function getUser(): Users
    {
        return $this->user;
    }
}