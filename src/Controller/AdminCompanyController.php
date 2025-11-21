<?php

namespace App\Controller;

use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\CompanyType;
use App\Repository\CompanyRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Form\CsvImportType;
use App\Import\Contract\ImporterFactory;
use App\Service\CsvImportService;

class AdminCompanyController extends AbstractController
{
    #[Route('/admin/companies', name: 'admin_companies')]
    public function companies(EntityManagerInterface $em, Request $request): Response
    {
        $companies = $em->getRepository(Company::class)->findAll();
        $importForm = $this->createForm(CsvImportType::class, null, [
            'action' => $this->generateUrl('admin_companies'),
            'method' => 'POST',
        ]);

        return $this->render('admin/companies.html.twig', [
            'companies' => $companies,
            'importForm' => $importForm->createView(),
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

}
