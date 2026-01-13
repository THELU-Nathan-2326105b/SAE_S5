<?php

namespace App\Repository;

use App\Entity\ResetPasswordRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Persistence\ResetPasswordRequestRepositoryInterface;

/**
 * ResetPasswordRequestRepository
 * 
 * Repository pour gérer les demandes de réinitialisation de mot de passe.
 * Implémente l'interface ResetPasswordRequestRepositoryInterface du bundle SymfonyCasts.
 * 
 * @extends ServiceEntityRepository<ResetPasswordRequest>
 * @package App\Repository
 */
class ResetPasswordRequestRepository extends ServiceEntityRepository implements ResetPasswordRequestRepositoryInterface
{
    /**
     * Constructeur du repository
     * 
     * @param ManagerRegistry $registry Registre du gestionnaire d'entités
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResetPasswordRequest::class);
    }

    /**
     * Crée une nouvelle demande de réinitialisation de mot de passe
     * 
     * @param object $user Utilisateur demandant la réinitialisation
     * @param \DateTimeInterface $expiresAt Date/heure d'expiration du token
     * @param string $selector Sélecteur du token
     * @param string $hashedToken Token hashé
     * @return ResetPasswordRequestInterface La demande créée
     */
    public function createResetPasswordRequest(
        object $user,
        \DateTimeInterface $expiresAt,
        string $selector,
        string $hashedToken
    ): ResetPasswordRequestInterface {
        $resetRequest = new ResetPasswordRequest(
            $user,
            $expiresAt,
            $selector,
            $hashedToken
        );

        $this->getEntityManager()->persist($resetRequest);
        $this->getEntityManager()->flush();

        return $resetRequest;
    }

    /**
     * Persiste une demande de réinitialisation en base de données
     * 
     * @param ResetPasswordRequestInterface $resetPasswordRequest La demande à persister
     * @return void
     */
    public function persistResetPasswordRequest(ResetPasswordRequestInterface $resetPasswordRequest): void
    {
        $this->getEntityManager()->persist($resetPasswordRequest);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouve une demande de réinitialisation par son sélecteur
     * 
     * @param string $selector Sélecteur du token
     * @return ?ResetPasswordRequestInterface La demande trouvée ou null
     */
    public function findResetPasswordRequest(string $selector): ?ResetPasswordRequestInterface
    {
        return $this->findOneBy(['selector' => $selector]);
    }

    /**
     * Récupère la date de la demande la plus récente non expirée pour un utilisateur
     * 
     * @param object $user Utilisateur
     * @return ?\DateTimeInterface Date de la demande ou null
     */
    public function getMostRecentNonExpiredRequestDate(object $user): ?\DateTimeInterface
    {
        $resetPasswordRequest = $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->orderBy('r.requestedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $resetPasswordRequest?->getRequestedAt();
    }

    /**
     * Supprime une demande de réinitialisation
     * 
     * @param ResetPasswordRequestInterface $resetPasswordRequest La demande à supprimer
     * @return void
     */
    public function removeResetPasswordRequest(ResetPasswordRequestInterface $resetPasswordRequest): void
    {
        $this->getEntityManager()->remove($resetPasswordRequest);
        $this->getEntityManager()->flush();
    }

    /**
     * Supprime tous les tokens de réinitialisation expirés
     * 
     * @return int Nombre de demandes supprimées
     */
    public function removeExpiredResetPasswordRequests(): int
    {
        return $this->createQueryBuilder('r')
            ->delete()
            ->where('r.expiresAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Récupère l'identifiant unique de l'utilisateur
     * 
     * @param object $user Utilisateur
     * @return string Identifiant de l'utilisateur
     */
    public function getUserIdentifier(object $user): string
    {
        /** @var \App\Entity\Users $user */
        return (string) $user->getId();
    }
}