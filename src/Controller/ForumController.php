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
         $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $cvUrl = $user->getUserUrlCv();
        $forum = $em->getRepository(Forum::class)->find(1);

        // Récupération des entreprises filtrées selon le niveau de l'éleve connectée
        $studentLevel = $user->getUserLevel();
        $companies = $companyRepository->findCompaniesForStudent($forum->getId(), $studentLevel);

        // Récupération des entreprises déjà sélectionnées
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
                    // On veut un sous dossier par user pour éviter toute erreur
                    $studentFolder = $this->getParameter('uploads_directory') . '/' . $user->getId();
                    if (!file_exists($studentFolder)) {
                        mkdir($studentFolder, 0755, true);
                    }

                    // Suppression de l'ancien CV
                    $oldCv = $user->getUserUrlCv();
                    if ($oldCv) {
                        $oldCvPath = $this->getParameter('kernel.project_dir') . '/public' . $oldCv;
                        if (file_exists($oldCvPath)) {
                            unlink($oldCvPath); // suppression du fichier précédent
                        }
                    }

                    // On ajoute le nouveau CV
                    $cvFile->move($studentFolder, $newFilename);
                    $cvUrl = '/uploads/' . $user->getId() . '/' . $newFilename;

                    // Maj BD
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
