<?php

namespace App\Controller;

use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->render('admin/admin.html.twig');
    }

    #[Route('/admin/users', name: 'admin_users')]
    public function users(): Response
    {
        return $this->render('admin/users.html.twig');
    }

    #[Route('/admin/companies', name: 'admin_companies')]
    public function companies(EntityManagerInterface $em): Response
    {
        $companies = $em->getRepository(Company::class)->findAll();

        return $this->render('admin/companies.html.twig', [
            'companies' => $companies
        ]);
    }

    #[Route('/admin/company/update', name: 'company_update', methods: ['POST'])]
    public function updateCompany(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['oldCompanyName']) || !isset($data['companyName'])) {
                return new JsonResponse([
                    'error' => 'Données invalides',
                    'received' => $data
                ], 400);
            }

            $company = $em->getRepository(Company::class)->findOneBy(['company_name' => $data['oldCompanyName']]);

            if (!$company) {
                return new JsonResponse([
                    'error' => 'Entreprise introuvable',
                    'searchedName' => $data['oldCompanyName']
                ], 404);
            }

            // Conserver le logo existant si non fourni dans la mise à jour
            $currentLogo = $company->getCompanyLogo();

            $company->setCompanyName($data['companyName']);
            $company->setCompanyDescription($data['companyDescription'] ?? '');
            // Préserver le logo existant si pas de nouveau logo fourni
            $company->setCompanyLogo($data['companyLogo'] ?? $currentLogo);

            $em->flush();

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'companyName' => $company->getCompanyName(),
                    'companyDescription' => $company->getCompanyDescription(),
                    'companyLogo' => $company->getCompanyLogo()
                ]
            ]);

        } catch (\Exception $e) {
            error_log('Erreur mise à jour entreprise: ' . $e->getMessage());
            return new JsonResponse([
                'error' => 'Erreur lors de la mise à jour',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/admin/company/create', name: 'company_create', methods: ['POST'])]
    public function createCompany(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['companyName']) || !isset($data['companyDescription'])) {
                return new JsonResponse([
                    'error' => 'Données invalides',
                    'received' => $data
                ], 400);
            }

            // Vérifier que l'entreprise n'existe pas déjà
            $existingCompany = $em->getRepository(Company::class)->findOneBy(['company_name' => $data['companyName']]);
            if ($existingCompany) {
                return new JsonResponse([
                    'error' => 'Une entreprise avec ce nom existe déjà'
                ], 409);
            }

            $company = new Company();
            $company->setCompanyName($data['companyName']);
            $company->setCompanyDescription($data['companyDescription']);
            $company->setCompanyLogo($data['companyLogo'] ?? null);

            $em->persist($company);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'companyName' => $company->getCompanyName(),
                    'companyDescription' => $company->getCompanyDescription(),
                    'companyLogo' => $company->getCompanyLogo()
                ]
            ], 201);

        } catch (\Exception $e) {
            error_log('Erreur création entreprise: ' . $e->getMessage());
            return new JsonResponse([
                'error' => 'Erreur lors de la création',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/admin/company/delete/{companyName}', name: 'company_delete', methods: ['DELETE'])]
    public function deleteCompany(string $companyName, EntityManagerInterface $em): JsonResponse
    {
        try {
            $company = $em->getRepository(Company::class)->findOneBy(['company_name' => $companyName]);

            if (!$company) {
                return new JsonResponse([
                    'error' => 'Entreprise introuvable'
                ], 404);
            }

            $em->remove($company);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Entreprise supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            error_log('Erreur suppression entreprise: ' . $e->getMessage());
            return new JsonResponse([
                'error' => 'Erreur lors de la suppression',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
