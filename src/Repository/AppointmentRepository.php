<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\Users;
use App\Entity\Forum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * AppointmentRepository
 * 
 * Repository pour gérer les requêtes sur l'entité Appointment.
 * Fournit des méthodes pour rechercher, ajouter et supprimer des rendez-vous.
 * 
 * @extends ServiceEntityRepository<Appointment>
 * @package App\Repository
 *
 * @method Appointment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Appointment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Appointment[]    findAll()
 * @method Appointment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AppointmentRepository extends ServiceEntityRepository
{
    /**
     * Constructeur du repository
     * 
     * @param ManagerRegistry $registry Registre du gestionnaire d'entités
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    /**
     * Ajoute un rendez-vous en base de données
     * 
     * @param Appointment $appointment Le rendez-vous à ajouter
     * @param bool $flush Si true, flush immédiatement les modifications
     * @return void
     */
    public function add(Appointment $appointment, bool $flush = true): void
    {
        $this->getEntityManager()->persist($appointment);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un rendez-vous de la base de données
     * 
     * @param Appointment $appointment Le rendez-vous à supprimer
     * @param bool $flush Si true, flush immédiatement les modifications
     * @return void
     */
    public function remove(Appointment $appointment, bool $flush = true): void
    {
        $this->getEntityManager()->remove($appointment);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Récupère les rendez-vous sélectionnés (appointment_request = TRUE)
     * pour un utilisateur et un forum donné
     *
     * @param Users $user L'utilisateur
     * @param Forum $forum Le forum
     * @return Appointment[] Tableau des rendez-vous sélectionnés
     */
    public function findSelectedByUserAndForum(Users $user, Forum $forum): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.forum = :forum')
            ->andWhere('a.appointmentRequest = true')
            ->setParameter('user', $user)
            ->setParameter('forum', $forum)
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime tous les rendez-vous d'un utilisateur pour un forum donné
     * Utile pour réinitialiser la sélection
     * 
     * @param Users $user L'utilisateur
     * @param Forum $forum Le forum
     * @return void
     */
    public function removeByUserAndForum($user, $forum): void
    {
        // $user et $forum doivent être des objets User et Forum, pas des IDs
        $qb = $this->createQueryBuilder('a');
        $qb->delete()
            ->where('a.user = :user')
            ->andWhere('a.forum = :forum')
            ->setParameter('user', $user)
            ->setParameter('forum', $forum)
            ->getQuery()
            ->execute();
    }
}
