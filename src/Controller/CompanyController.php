<?php

namespace App\Controller;

use App\Entity\Company;
use App\Form\CompanyType;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Form\CsvImportType;
use App\Import\Contract\ImporterFactory;
use App\Service\CsvImportService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use App\Service\IsPresentImportService;

/**
 * Contrôleur de gestion des entreprises (Company).
 *
 * Préfixe de route: /company 
 */
#[Route('/admin/company', name: 'app_company_')]
class CompanyController extends AbstractController
{
    // private function accessControl(): void
    // {
    //     //$this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Accès réservé aux administrateurs.');
    // }
    /**
     * Liste toutes les entreprises.
     *
     * @param CompanyRepository $companyRepository Repository Doctrine pour Company.
     * @return Response Vue listant les entreprises.
     *
     * Route: GET /company (name: app_company_index)
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(CompanyRepository $companyRepository): Response{
        // Récupère toutes les entreprises triées par nom via le repository custom
        $companies = $companyRepository->findAllOrderedByName();
        $importForm = $this->createForm(CsvImportType::class, null, [
            'action' => $this->generateUrl('app_company_import'), 
            'method' => 'POST',
        ]);
        
        return $this->render('company/index.html.twig', [
            'companies' => $companies,
            'importForm' => $importForm->createView(),
        ]);
    }

    /**
     * Crée une nouvelle entreprise.
     *
     * - Construit et traite le formulaire CompanyType.
     * - Sauvegarde l’entité si le formulaire est valide.
     *
     * @param Request $request Requête HTTP (GET pour afficher, POST pour soumettre).
     * @param EntityManagerInterface $em Gestionnaire Doctrine.
     * @return Response
     *
     * Route: GET|POST /company/new (name: app_company_new)
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, CompanyRepository $repo): Response{
        $company = new Company();

        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier que l'entreprise n'existe pas déjà
            if ($repo->find($company->getCompanyName())) {
                $this->addFlash('error', 'Cette entreprise existe déjà.');
                return $this->redirectToRoute('app_company_index');
            }

            try {
                $em->persist($company);
                $em->flush();
                $this->addFlash('success', 'Entreprise créée avec succès.');
                return $this->redirectToRoute('app_company_index');
            } catch (UniqueConstraintViolationException $e) {
                $this->addFlash('error', 'Cette entreprise existe déjà.');
                return $this->redirectToRoute('app_company_index');
            }
        }

        return $this->render('company/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Affiche le détail d’une entreprise.
     *
     * ParamConverter: Symfony injecte automatiquement l’entité Company
     * grâce au {company_name} passé en URL (clé primaire).
     *
     * @param Company $company Entreprise ciblée.
     * @return Response Vue détail.
     *
     * Route: GET /company/{company_name} (name: app_company_show)
     */
    #[Route('/{company_name}', name: 'show', methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['company_name' => 'company_name'])]Company $company): Response {
        return $this->render('company/show.html.twig', [
            'company' => $company,
        ]);
    }


    /**
     * Édite une entreprise existante.
     *
     * - Construit le formulaire CompanyType pré-rempli.
     * - Sauvegarde les modifications si valide.
     *
     * @param Request $request
     * @param Company $company Entité injectée.
     * @param EntityManagerInterface $em
     * @return Response
     *
     * Route: GET|POST /company/{company_name}/edit (name: app_company_edit)
     */


    #[Route('/{company_name}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request,#[MapEntity(mapping: ['company_name' => 'company_name'])] Company $company,EntityManagerInterface $em): Response {
        // $this->accessControl();
        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Entreprise mise à jour.');

            return $this->redirectToRoute('app_company_show', ['company_name' => $company->getCompanyName()]);
        }
        return $this->render('company/edit.html.twig', ['form' => $form->createView(),'company' => $company,]);
    }


    /**
     * Supprime une entreprise (POST avec CSRF).
     *
     * @param Request $request
     * @param Company $company
     * @param EntityManagerInterface $em
     * @return Response
     *
     * Route: POST /company/{company_name}/delete (name: app_company_delete)
     */
    #[Route('/{company_name}/delete', name: 'delete', methods: ['GET', 'POST'])]
    public function delete(Request $request,#[MapEntity(mapping: ['company_name' => 'company_name'])] Company $company,EntityManagerInterface $em): Response {
        // $this->accessControl();
        $name = $company->getCompanyName();
        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('delete'.$name, $request->request->get('_token'))) {
                $em->remove($company);
                $em->flush();
                $this->addFlash('success', 'Entreprise supprimée.');
                return $this->redirectToRoute('app_company_index', [], Response::HTTP_SEE_OTHER);
            }

            // Token invalide -> message d’erreur + retour sur la fiche
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_company_show', ['company_name' => $name], Response::HTTP_SEE_OTHER);
        }

        // GET : page de confirmation
        return $this->render('company/delete.html.twig', [
            'company' => $company,
        ]);
    }


    /**
     * Supprime toutes les entreprises.
     *
     * @param Request $request
     * @param CompanyRepository $companyRepository
     * @param EntityManagerInterface $em
     * @return Response
     */
    #[Route('/delete-all', name: 'delete_all', methods: ['POST'])]
    public function deleteAll(Request $request, CompanyRepository $companyRepository, EntityManagerInterface $em): Response
    {
        // Vérification du token CSRF pour la sécurité
        if ($this->isCsrfTokenValid('delete_all_companies', $request->request->get('_token'))) {
            
            $companies = $companyRepository->findAll();
            $count = count($companies);

            foreach ($companies as $company) {
                $em->remove($company);
            }
            
            $em->flush();

            if ($count > 0) {
                $this->addFlash('success', "$count entreprises ont été supprimées avec succès.");
            } else {
                $this->addFlash('info', "Aucune entreprise à supprimer.");
            }
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('app_company_index');
    }
    /**
     * Importe des entreprises depuis un fichier CSV uploadé.
     *
     * Route: POST /company/import (name: app_company_import)
     */
    #[Route('/import', name: 'import', methods: ['POST'])]
    public function import(
        Request $request, 
        EntityManagerInterface $em, 
        ImporterFactory $importerFactory, 
        CsvImportService $csvImportService,
        IsPresentImportService $isPresentService
    ): Response
    {
        $form = $this->createForm(CsvImportType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('warning', 'Le fichier fourni n\'est pas valide.');
            return $this->redirectToRoute('app_company_index');
        }

       try {
            $uploaded = $form->get('csvFile')->getData();
            $path = $csvImportService->moveUploadedCsvAndGetPath($uploaded, 'company_import_');
            
            // Import des entreprises
            $companies = $csvImportService->runImport($path, $importerFactory, 'company');
            
            try {
                $csvImportService->persistImportedEntities($companies, $em);
            } catch (UniqueConstraintViolationException $e) {
                // Extraire le nom de l'entreprise en doublon du message d'erreur
                preg_match('/Key \(company_name\)=\(([^)]+)\)/', $e->getMessage(), $matches);
                $companyName = $matches[1] ?? 'inconnue';
                throw new \RuntimeException("L'entreprise '{$companyName}' existe déjà en base de données.");
            }
            
            // Import dans is_present (si les colonnes existent)
            $isPresentRows = $this->extractIsPresentDataFromCsv($path);
            if (!empty($isPresentRows)) {
                $countPresent = $isPresentService->importFromCsv($isPresentRows);
                $this->addFlash('success', "Import terminé : {$csvImportService->countItems($companies)} entreprises, {$countPresent} présences forum.");
            } else {
                $this->addFlash('success', "Import terminé avec succès ({$csvImportService->countItems($companies)} entreprises).");
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Échec de l\'import : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_company_index');
    }

    /**
     * Extrait les données is_present du CSV
     */
    private function extractIsPresentDataFromCsv(string $path): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        
        // Lire les headers
        $headers = $file->fgetcsv(',');
        if (!$headers) {
            return [];
        }
        
        // Nettoyer BOM UTF-8
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        $headers = array_map('trim', $headers);
        $headers = array_map('strtolower', $headers);
        
        // Vérifier si les colonnes is_present existent (start_time et end_time minimum requis)
        if (!in_array('start_time', $headers) || !in_array('end_time', $headers)) {
            return []; // Pas de données is_present dans ce CSV
        }
        
        $rows = [];
        while (!$file->eof()) {
            $row = $file->fgetcsv(',');
            if (!$row || count($row) < count($headers)) {
                continue;
            }
            
            $assoc = array_combine($headers, $row);
            if ($assoc) {
                // Ajouter forum_id = 1 si absent
                if (empty($assoc['forum_id'])) {
                    $assoc['forum_id'] = 1;
                }
                
                // Vérifier que company_name existe
                if (!empty($assoc['company_name'])) {
                    $rows[] = $assoc;
                }
            }
        }
        
        return $rows;
    }

}
