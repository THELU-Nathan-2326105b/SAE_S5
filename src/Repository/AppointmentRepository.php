<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\Users;
use App\Entity\Forum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 *
 * @method Appointment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Appointment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Appointment[]    findAll()
 * @method Appointment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    public function add(Appointment $appointment, bool $flush = true): void
    {
        $this->getEntityManager()->persist($appointment);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Appointment $appointment, bool $flush = true): void
    {
        $this->getEntityManager()->remove($appointment);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Récupère les appointments déjà cochés (appointment_request = TRUE)
     * pour un utilisateur et un forum donné
     *
     * @param int $userId
     * @param int $forumId
     * @return Appointment[]
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
     * Supprime tous les appointments d'un utilisateur pour un forum donné
     * (utile pour reseter la sélection)
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
