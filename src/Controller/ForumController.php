<?php

namespace App\Controller;

use App\Entity\Forum;
use App\Entity\Users;
use App\Repository\CompanyRepository;
use App\Repository\AppointmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Repository\UsersRepository;

class ForumController extends AbstractController
{
    #[Route('/forum', name: 'forum')]
    public function index(
        Request $request,
        CompanyRepository $companyRepository,
        AppointmentRepository $appointmentRepository,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        UsersRepository $usersRepository
    ): Response {
        // Récupération de l'utilisateur depuis la session
        $sessionUser = $request->getSession()->get('user');

        if (!$sessionUser) {
            $this->addFlash('error', 'Vous devez être connecté pour accéder au forum.');
            return $this->redirectToRoute('login');
        }

        /** @var Users $user */
        $user = $usersRepository->find($sessionUser['id']);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('login');
        }

        // CV actuel
        $cvUrl = $user->getUserUrlCv();

        // Gestion de l'upload du CV
        if ($request->isMethod('POST') && $request->files->get('cv')) {
            $cvFile = $request->files->get('cv');
            if ($cvFile) {
                if (!in_array($cvFile->guessExtension(), ['pdf', 'doc', 'docx'])) {
                    $this->addFlash('error', 'Format de fichier non autorisé.');
                    return $this->redirectToRoute('forum');
                }

                $originalFilename = pathinfo($cvFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $cvFile->guessExtension();

                try {
                    $cvFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    $cvUrl = '/uploads/' . $newFilename;

                    $user->setUserUrlCv($cvUrl);
                    $em->persist($user);
                    $em->flush();

                    $this->addFlash('success', 'CV envoyé avec succès !');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de l’envoi du CV.');
                }
            }
        }

        // Récupération des entreprises
        $companies = $companyRepository->findAllOrderedByName();

        // Récupération du forum actuel (ici on prend le premier comme exemple)
        $forum = $em->getRepository(Forum::class)->find(1); // ou récupère selon ton besoin

        // Récupération des appointments déjà cochés
        $selectedAppointments = $appointmentRepository->findSelectedByUserAndForum($user, $forum);
        $selectedCompanies = array_map(fn($a) => $a->getCompanyName(), $selectedAppointments);

        // Gestion des nouvelles sélections envoyées par POST
        if ($request->isMethod('POST') && $request->request->get('entreprises')) {
            $selectedFromForm = $request->request->get('entreprises', []);
            if (!is_array($selectedFromForm)) {
                $selectedFromForm = [$selectedFromForm];
            }

            // Supprime tous les anciens appointments pour ce forum et utilisateur
            $appointmentRepository->removeByUserAndForum($user, $forum);

            // Ajoute les nouvelles sélections
            foreach ($selectedFromForm as $companyName) {
                $appointment = new \App\Entity\Appointment();
                $appointment->setUser($user);
                $appointment->setForum($forum);
                $appointment->setCompanyName($companyName);
                $appointment->setAppointmentRequest(true);
                $appointment->setAppointmentTime(new \DateTime()); // met l'heure actuelle
                $em->persist($appointment);
            }
            $em->flush();

            // Met à jour la liste des présélections
            $selectedCompanies = $selectedFromForm;
        }

        return $this->render('forum/forum.html.twig', [
            'companies' => $companies,
            'selectedCompanies' => $selectedCompanies,
            'cvUrl' => $cvUrl,
        ]);
    }
}
