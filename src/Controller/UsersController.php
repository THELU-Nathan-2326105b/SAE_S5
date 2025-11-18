<?php

namespace App\Controller;

use App\Entity\Users;
use App\Form\UsersType;
use App\Form\UsersCsvImportType;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Import\Contract\ImporterFactory;



/**
 * Contrôleur de gestion des utilisateurs.
 *
 * Préfixe de route: "/user" (voir l'attribut sur la classe).
 * Les méthodes exposent : index (liste), show (détail), new (création),
 * edit (édition), delete (suppression).
 */
#[Route('/users', name: 'app_user_')]
class UsersController extends AbstractController
{


    /**
     * Liste tous les utilisateurs.
     *
     * @param UsersRepository $UsersRepository Repository Doctrine pour l'entité Users.
     * @return Response Vue listant les utilisateurs.
     *
     * Route: GET /user (name: app_user_index)
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, UsersRepository $UsersRepository): Response
    {
        $users = $UsersRepository->findAll();
        $sessionUser = $request->getSession()->get('user');

        $importForm = $this->createForm(UsersCsvImportType::class, null, [
            'action' => $this->generateUrl('app_user_import'), 
            'method' => 'POST',
        ]);

        return $this->render('user/index.html.twig', [
            'users'      => $users,
            'user'       => $sessionUser,
            'importForm' => $importForm->createView(),  
        ]);
    }



    /**
     * Affiche le détail d’un utilisateur.
     *
     * ParamConverter: Symfony injecte automatiquement l’entité Users grâce à l’{id}.
     *
     * @param Users $user Utilisateur ciblé.
     * @return Response Vue détail.
     *
     * Route: GET /user/{id} (name: app_user_show)
     * Contrainte: id numérique via regex <\d+>
     */
    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(Users $user): Response{
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }


     /**
     * Crée un nouvel utilisateur.
     *
     * - Initialise la date de dernière connexion au "jour courant".
     * - Construit et traite le formulaire UsersType.
     * - Hash le mot de passe si renseigné (champ 'plainPassword' du formulaire).
     *
     * @param Request $request Requête HTTP (GET pour afficher, POST pour soumettre).
     * @param EntityManagerInterface $em Gestionnaire Doctrine pour persister l'entité.
     * @return Response
     *
     * Route: GET|POST /user/new (name: app_user_new)
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response{

        // Nouvelle entité Users avec valeur par défaut
        $user = new Users();
        $user->setUserLastconnexion(new \DateTimeImmutable('today'));

        // Le formulaire possède une option custom 'require_password' (gérée dans UsersType)
        $form = $this->createForm(UsersType::class, $user, [
            'require_password' => true,
        ]);
        $form->handleRequest($request);

        // if($request->isMethod('POST')){ 
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string|null $plainPassword */
            // dd($user);
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setUserPwd(password_hash($plainPassword, PASSWORD_BCRYPT));
            }


             // Persistance & flush
            $em->persist($user);
            $em->flush();

             // Feedback utilisateur
            $this->addFlash('success', 'Utilisateur créé avec succès.');

            // Redirection vers la liste
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        // Affichage du formulaire (GET ou POST non soumis/invalidé)
        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }


    /**
     * Édite un utilisateur existant.
     *
     * - Construit le formulaire en mode 'require_password' = false (mot de passe optionnel).
     * - Si un nouveau mot de passe est fourni, on le hash avant sauvegarde.
     *
     * @param Request $request
     * @param Users $user Entité injectée via ParamConverter.
     * @param EntityManagerInterface $em
     * @return Response
     *
     * Route: GET|POST /user/{id}/edit (name: app_user_edit)
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Users $user, EntityManagerInterface $em): Response{
        $form = $this->createForm(UsersType::class, $user, [
            'require_password' => false, 
        ]);
        $form->handleRequest($request);

        // if($request->isMethod('POST')){ 
        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setUserPwd(password_hash($plainPassword, PASSWORD_BCRYPT));
            }

            $em->flush();

            $this->addFlash('success', 'Utilisateur mis à jour.');

            // Redirige vers la fiche de l’utilisateur édité
            return $this->redirectToRoute(
                    'app_user_show',
                    ['id' => $user->getUserId()],
                    Response::HTTP_SEE_OTHER
                );
        }
        // Affichage du formulaire
        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }


    /**
     * Supprime un utilisateur (confirmation + CSRF).
     *
     * - GET : affiche la page de confirmation avec un formulaire contenant le token CSRF.
     * - POST : vérifie le token, supprime l’entité, redirige avec message flash.
     *
     * @param Request $request
     * @param Users $user
     * @param EntityManagerInterface $em
     * @return Response
     *
     * Route: GET|POST /user/{id}/delete (name: app_user_delete)
     */
    #[Route('/{id<\d+>}/delete', name: 'delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, Users $user, EntityManagerInterface $em): Response{
        if ($request->isMethod('POST')) {
            $id = $user->getUserId();

            // Vérification CSRF: le token doit être généré côté vue avec le même id ('delete'.$id)
            if ($this->isCsrfTokenValid('delete'.$id, $request->request->get('_token'))) {
                $em->remove($user);
                $em->flush();
                $this->addFlash('success', 'Utilisateur supprimé.');
                return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
            }


            // Token invalide -> message d’erreur + retour sur la fiche
            $this->addFlash('error', 'Token CSRF invalide.');
            // Redirige vers la fiche de l’utilisateur édité
            return $this->redirectToRoute('app_user_show', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/delete.html.twig', ['user' => $user, ]);
    }

    /**
     * Importe des utilisateurs depuis un fichier CSV uploadé.
     *
     * - Affiche un formulaire d’upload sur la page index .
     * - Vérifie et traite le formulaire UsersCsvImportType.
     * - Déplace le fichier dans var/tmp.
     * - Utilise ImporterFactory pour récupérer l’importeur CSV des "users".
     * - Convertit chaque ligne du CSV en entité Users, puis les persiste en base.
     * - Ajoute un message flash de succès ou d’erreur selon le résultat.
     *
     * @param Request              $request         Requête HTTP contenant le fichier
     * @param EntityManagerInterface $em            Gestionnaire Doctrine pour sauvegarder les entités.
     * @param ImporterFactory      $importerFactory Factory fournissant l’importeur adapté (ici CSV/users).
     *
     * @return Response            Redirection vers la liste des utilisateurs (route app_user_index).
     *
     * Route: POST /users/import (name: app_user_import)
     */
    #[Route('/import', name: 'import', methods: ['POST'])]
    public function import(Request $request,EntityManagerInterface $em ,ImporterFactory $importerFactory): Response
    {
        $form = $this->createForm(UsersCsvImportType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('warning', 'Le fichier fourni n’est pas valide.');
            return $this->redirectToRoute('app_user_index');
        }

        $uploaded  = $form->get('csvFile')->getData();
        $targetDir = $this->getParameter('kernel.project_dir') . '/var/tmp';
        $filename= 'users_import_' . uniqid() . '.csv';
        $path= $uploaded->move($targetDir, $filename)->getPathname();

        try {
            $importer = $importerFactory->create('users', 'csv');
            $result = $importer->import($path,'users'); 
            foreach ($result as $key => $value) {
                $em->persist($value);
                $em->flush();
            }
         $this->addFlash('success', "Import terminé avec succès.");
            
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Échec de l’import : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_user_index');
    }


}
