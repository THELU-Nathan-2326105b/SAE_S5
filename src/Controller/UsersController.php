<?php

namespace App\Controller;

use App\Entity\Users;
use App\Form\UsersType;
use App\Form\CsvImportType;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Import\Contract\ImporterFactory;
use App\Service\CsvImportService;



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

    private function accessControl(string $role='nobody'):bool {
        return $role !== 'admin';
        // if($role !== 'admin') {
        //     return $this->redirect('/');
        // }
        // Accès réservé aux administrateurs
        //$this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Accès non autorisé.');
    }
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
        if($sessionUser === null){
            return $this->redirect('/login');
        }
        if($this->accessControl($sessionUser['role'])) {
            return $this->redirect('/');
        }

        $importForm = $this->createForm(CsvImportType::class, null, [
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
    public function show(Request $request,Users $user): Response{
        $sessionUser = $request->getSession()->get('user');
        if($this->accessControl($sessionUser['role'])) {
            return $this->redirect('/');
        }
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

        $sessionUser = $request->getSession()->get('user');
        if($sessionUser === null){
            return $this->redirect('/login');
        }
        if($this->accessControl($sessionUser['role'])) {
            return $this->redirect('/');
        }

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
        $sessionUser = $request->getSession()->get('user');
       if ($sessionUser === null) {
            return $this->redirect('/login');
        }
        else{
            if ($this->accessControl($sessionUser['role'])) {
                return $this->redirect('/');
            }
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
                        ['id' => $user->getId()],
                        Response::HTTP_SEE_OTHER
                    );
            }
            // Affichage du formulaire
            return $this->render('user/edit.html.twig', [
                'user' => $user,
                'form' => $form->createView(),
            ]);
        }

        
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
        $sessionUser = $request->getSession()->get('user');
        if($this->accessControl($sessionUser['role'])) {
            return $this->redirect('/');
        }
        if ($request->isMethod('POST')) {
            $id = $user->getId();

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
    public function import(
        Request $request, EntityManagerInterface $em, 
        ImporterFactory $importerFactory, CsvImportService $csvImportService): Response {
        
        $sessionUser = $request->getSession()->get('user');
        if($this->accessControl($sessionUser['role'])) {
            return $this->redirect('/');
        }
        
        $form = $this->createForm(CsvImportType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('warning', 'Le fichier fourni n’est pas valide.');
            return $this->redirectToRoute('app_user_index');
        }

        try {
            $uploaded = $form->get('csvFile')->getData();
            $path = $csvImportService->moveUploadedCsvAndGetPath($uploaded, 'users_import_');
            $users = $csvImportService->runImport($path, $importerFactory, 'users');
            $csvImportService->persistImportedEntities($users, $em);
            $this->addFlash('success', "Import terminé avec succès ({$csvImportService->countItems($users)} utilisateurs).");
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Échec de l’import : ' /*. $e->getMessage()*/);
        }

        return $this->redirectToRoute('app_user_index');
    }


    /**
     * Supprime tous les utilisateurs qui ne sont pas 'admin'.
     */
    #[Route('/delete-non-admins', name: 'delete_non_admins', methods: ['POST'])]
    public function deleteAllNonAdmins(Request $request, EntityManagerInterface $em, UsersRepository $usersRepository): Response
    {
        // 1. Vérification des droits (Admin seulement)
        $sessionUser = $request->getSession()->get('user');
        if ($this->accessControl($sessionUser['role'])) {
            return $this->redirect('/');
        }

        // 2. Vérification CSRF pour éviter les suppressions accidentelles via des liens malveillants
        if ($this->isCsrfTokenValid('delete_all_non_admins', $request->request->get('_token'))) {
            
            // Appel de la méthode privée pour effectuer la suppression
            $deletedCount = $this->removeNonAdminUsers($em, $usersRepository);

            if ($deletedCount > 0) {
                $this->addFlash('success', "$deletedCount utilisateurs non-admin ont été supprimés.");
            } 
            else {
                $this->addFlash('info', "Aucun utilisateur à supprimer.");
            }
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('app_user_index');
    }

    /**
     * Méthode privée pour identifier et supprimer les non-admins.
     * Retourne le nombre d'utilisateurs supprimés.
     */
    private function removeNonAdminUsers(EntityManagerInterface $em, UsersRepository $repo): int
    {
        $users = $repo->findAll();
        $count = 0;
        foreach ($users as $user) {
            if ($user->getUserRole() !== 'admin') {
                $em->remove($user);
                $count++;
            }
        }
        $em->flush();

        return $count;
    }




}
