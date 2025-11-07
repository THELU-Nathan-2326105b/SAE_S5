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

    #[Route('/admin/users', name: 'admin-users')]
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
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['companyName'])) {
            return new JsonResponse(['error' => 'Données invalides'], 400);
        }

        $company = $em->getRepository(Company::class)->find($data['companyName']);
        if (!$company) {
            return new JsonResponse(['error' => 'Entreprise introuvable'], 404);
        }

        $company->setCompanyDescription($data['companyDescription'] ?? '');
        $company->setCompanyLogo($data['companyLogo'] ?? '');
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/admin/company/delete/{name}', name: 'company_delete', methods: ['DELETE'])]
    public function deleteCompany(string $name, EntityManagerInterface $em): JsonResponse
    {
        $company = $em->getRepository(Company::class)->find($name);
        if (!$company) {
            return new JsonResponse(['error' => 'Entreprise introuvable'], 404);
        }

        $em->remove($company);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
