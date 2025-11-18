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
        UsersRepository $usersRepository,
        SluggerInterface $slugger
    ): Response {
        $sessionUser = $request->getSession()->get('user');
        if (!$sessionUser) return $this->redirectToRoute('login');

        $user = $usersRepository->find($sessionUser['id']);
        if (!$user) return $this->redirectToRoute('login');

        $forum = $em->getRepository(Forum::class)->find(1);

        // --- Gestion des entreprises ---
        $companies = $companyRepository->findAllOrderedByName();
        $selectedAppointments = $appointmentRepository->findSelectedByUserAndForum($user, $forum);
        $selectedCompanies = array_map(fn($a) => $a->getCompanyName(), $selectedAppointments);

        $showCvSection = false;

        if ($request->isMethod('POST') && $request->request->get('entreprises')) {
            $selectedFromForm = $request->request->get('entreprises', []);
            if (!is_array($selectedFromForm)) $selectedFromForm = [$selectedFromForm];

            $appointmentRepository->removeByUserAndForum($user, $forum);

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
            $showCvSection = true; // Affiche la section CV après validation
        }

        // --- Gestion du CV ---
        $cvUrl = $user->getUserUrlCv();
        if ($request->isMethod('POST') && $request->files->get('cv')) {
            $cvFile = $request->files->get('cv');
            if ($cvFile && in_array($cvFile->guessExtension(), ['pdf', 'doc', 'docx'])) {
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
            $showCvSection = true;
        }

        return $this->render('forum/forum.html.twig', [
            'companies' => $companies,
            'selectedCompanies' => $selectedCompanies,
            'cvUrl' => $cvUrl,
            'showCvSection' => $showCvSection,
        ]);
    }

}
