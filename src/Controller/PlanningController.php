<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\AppointmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * PlanningController
 * 
 * Contrôleur responsable de l'affichage du planning personnel des rendez-vous.
 * Affiche les rendez-vous planifiés de l'utilisateur connecté.
 * 
 * @package App\Controller
 */
class PlanningController extends AbstractController
{
    /**
    * Affiche le planning personnel des rendez-vous de l'utilisateur connecté
     * 
     * @param Request $request La requête HTTP courante
     * @param AppointmentRepository $appointmentRepository Repository pour les rendez-vous
     * @param UsersRepository $usersRepository Repository pour les utilisateurs
     * @return Response Le template du planning avec les rendez-vous de l'utilisateur
     */
    #[Route('/planning', name: 'planning')]
    #[IsGranted('ROLE_USER')]
    public function index(AppointmentRepository $appointmentRepository): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        // Si getUser() retourne null (ne devrait pas arriver grâce à IsGranted)
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        // Récupérer les rendez-vous planifiés de l'utilisateur
        $appointments = $appointmentRepository->createQueryBuilder('a')
            ->addSelect('f')
            ->join('a.forum', 'f')
            ->where('a.user = :user')
            ->andWhere('a.appointmentTime IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('a.appointmentTime', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('planning/planningalgo.html.twig', [
            'appointments' => $appointments,
        ]);
    }
}
