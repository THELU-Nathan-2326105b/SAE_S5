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
use Symfony\Component\Routing\Annotation\Route;
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
        $sessionUser = $request->getSession()->get('user');
        if (!$sessionUser) return $this->redirectToRoute('login');

        $user = $usersRepository->find($sessionUser['id']);
        if (!$user) return $this->redirectToRoute('login');

        $cvUrl = $user->getUserUrlCv();
        $forum = $em->getRepository(Forum::class)->find(1);
        $companies = $companyRepository->findAllOrderedByName();

        // Récupérer les entreprises déjà sélectionnées
        $selectedAppointments = $appointmentRepository->findSelectedByUserAndForum($user, $forum);
        $selectedCompanies = array_map(fn($a) => $a->getCompanyName(), $selectedAppointments);

        $showCv = false; // par défaut, Step 2 caché

        // --- Step 1 : validation entreprises ---
        if ($request->isMethod('POST') && $request->request->has('submit_entreprises')) {
            $selectedFromForm = $request->request->all('entreprises') ?? [];
            if (!is_array($selectedFromForm)) $selectedFromForm = [$selectedFromForm];

            // Supprimer les anciens appointments
            $oldAppointments = $appointmentRepository->findSelectedByUserAndForum($user, $forum);
            foreach ($oldAppointments as $oldAppointment) $em->remove($oldAppointment);
            $em->flush();
            foreach ($oldAppointments as $oldAppointment) $em->detach($oldAppointment);

            // Persister les nouvelles sélections
            foreach ($selectedFromForm as $companyName) {
                $appointment = new \App\Entity\Appointment();
                $appointment->setUser($user);
                $appointment->setForum($forum);
                $appointment->setCompanyName($companyName);
                $appointment->setAppointmentRequest(true);
                $appointment->setAppointmentTime(new \DateTime());
                $em->persist($appointment);
            }
            $em->flush();

            $selectedCompanies = $selectedFromForm;
            $showCv = true; // Step 2 devient visible
        }

        // --- Step 2 : traitement CV ---
        if ($request->isMethod('POST') && $request->request->has('submit_cv') && $request->files->get('cv')) {
            $cvFile = $request->files->get('cv');
            if ($cvFile && in_array($cvFile->guessExtension(), ['pdf','doc','docx'])) {
                $originalFilename = pathinfo($cvFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $cvFile->guessExtension();
                try {
                    $cvFile->move($this->getParameter('uploads_directory'), $newFilename);
                    $cvUrl = '/uploads/' . $newFilename;

                    $user->setUserUrlCv($cvUrl);
                    $em->persist($user);
                    $em->flush();

                    $this->addFlash('success', 'CV envoyé avec succès !');
                } catch (\Exception) {
                    $this->addFlash('error', 'Erreur lors de l’envoi du CV.');
                }
            } else {
                $this->addFlash('error', 'Format de fichier non autorisé.');
            }
        }

        return $this->render('forum/forum.html.twig', [
            'companies' => $companies,
            'selected' => $selectedCompanies,
            'cvUrl' => $cvUrl,
            'showCv' => $showCv,
        ]);
    }

}
