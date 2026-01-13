<?php

namespace App\Controller;

use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->render('admin/admin.html.twig');
    }
    #[Route('/admin/reset-data', name: 'admin_reset_data', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function resetData(
        Request $request,
        EntityManagerInterface $em
    ): Response {

        if (!$this->isCsrfTokenValid('reset_data', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide');
            return $this->redirectToRoute('admin');
        }

        try {
            $conn = $em->getConnection();

            //Supprimer toutes les tables SAUF users
            $conn->executeStatement('
                TRUNCATE TABLE
                    appointment,
                    is_present,
                    forum,
                    company,
                    reset_password_request
                CASCADE
            ');

            //Supprimer tous les users NON ADMIN
            //adapte selon ton champ (roles ou user_role)
            $conn->executeStatement("
                DELETE FROM users
                WHERE user_role NOT LIKE 'admin'
            ");

            // 3️⃣ Réinitialiser la séquence users
            $conn->executeStatement("
                SELECT setval('users_user_id_seq', COALESCE(MAX(user_id), 1))
                FROM users
            ");

            $this->addFlash('success', 'Réinitialisation effectuée (admins conservés)');

        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin');
    }

    
}
