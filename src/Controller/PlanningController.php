<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\AppointmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PlanningController extends AbstractController
{
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
