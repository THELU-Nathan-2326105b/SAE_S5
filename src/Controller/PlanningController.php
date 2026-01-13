<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\AppointmentRepository;
use App\Repository\UsersRepository;

/**
 * PlanningController
 * 
 * Contrôleur responsable de l'affichage du planning personnel des rendez-vous.
 * Affiche les rendez-vous planifiés de l'utilisateur connecté.
 * 
 * @package App\Controller
 */
final class PlanningController extends AbstractController
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
    public function index(Request $request, AppointmentRepository $appointmentRepository, UsersRepository $usersRepository): Response
    {
        // Récupérer l'utilisateur depuis la session
        $sessionUser = $request->getSession()->get('user');
        
        if (!$sessionUser) {
            $this->addFlash('error', 'Vous devez être connecté pour consulter votre emploi du temps.');
            return $this->redirectToRoute('login');
        }

        // Récupérer l'utilisateur en base
        $user = $usersRepository->find($sessionUser['id']);
        
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        // Récupérer tous les rendez-vous de l'utilisateur avec un horaire planifié
        $appointments = $appointmentRepository->createQueryBuilder('a')
            ->select('a', 'f')
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