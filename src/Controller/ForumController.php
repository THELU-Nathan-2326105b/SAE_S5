<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ForumController extends AbstractController
{
    #[Route('/forum', name: 'forum')]
    public function index(Request $request, CompanyRepository $companyRepository, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        //Récupération de toutes les entreprises
        $companies = $companyRepository->findAllOrderedByName();

        //Récupération des entreprises sélectionnées
        $selected = $request->request->all('entreprises');

        // Récupération de l'utilisateur connecté
        $user = $this->getUser();

        $cvUrl = $user?->getUserUrlCv();

        // Gestion de l'upload du CV
        if ($request->isMethod('POST') && $request->files->get('cv')) {
            $cvFile = $request->files->get('cv');

            if ($cvFile) {
                // Vérification du type de fichier
                if (!in_array($cvFile->guessExtension(), ['pdf', 'doc', 'docx'])) {
                    $this->addFlash('error', 'Format de fichier non autorisé.');
                    return $this->redirectToRoute('forum');
                }

                $originalFilename = pathinfo($cvFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $cvFile->guessExtension();

                try {
                    // Déplacement du fichier dans /public/uploads
                    $cvFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );

                    // Génération de l'URL publique
                    $cvUrl = '/uploads/' . $newFilename;

                    // Enregistrement de l'URL dans la base de données
                    $user->setUserUrlCv($cvUrl);
                    $em->persist($user);
                    $em->flush();

                    $this->addFlash('success', 'CV envoyé avec succès !');
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l’envoi du CV.');
                }
            }
        }

        return $this->render('forum/forum.html.twig', [
            'companies' => $companies,
            'selected' => $selected,
            'cvUrl' => $cvUrl,
        ]);
    }
}
