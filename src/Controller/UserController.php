<?php

namespace App\Controller;

use App\Entity\Users;
use App\Form\UsersType;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/user', name: 'app_user_')]
class UserController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(UsersRepository $usersRepository): Response{
        return $this->render('user/index.html.twig', [
            'users' => $usersRepository->findAll(),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(Users $user): Response{
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response{
        $user = new Users();
        $user->setUserLastconnexion(new \DateTimeImmutable('today'));


        $form = $this->createForm(UsersType::class, $user, [
            'require_password' => true,
        ]);
        $form->handleRequest($request);

        if($request->isMethod('POST')){ 
            /** @var string|null $plainPassword */
            // dd($user);
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setUserPwd(password_hash($plainPassword, PASSWORD_BCRYPT));
            }

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès.');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Users $user, EntityManagerInterface $em): Response{
        $form = $this->createForm(UsersType::class, $user, [
            'require_password' => false, 
        ]);
        $form->handleRequest($request);

        if($request->isMethod('POST')){ 
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setUserPwd(password_hash($plainPassword, PASSWORD_BCRYPT));
            }

            $em->flush();

            $this->addFlash('success', 'Utilisateur mis à jour.');
            return $this->redirectToRoute(
                    'app_user_show',
                    ['id' => $user->getUserId()],
                    Response::HTTP_SEE_OTHER
                );
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    
    #[Route('/{id<\d+>}/delete', name: 'delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, Users $user, EntityManagerInterface $em): Response{
        if ($request->isMethod('POST')) {
            $id = $user->getUserId();
            if ($this->isCsrfTokenValid('delete'.$id, $request->request->get('_token'))) {
                $em->remove($user);
                $em->flush();
                $this->addFlash('success', 'Utilisateur supprimé.');
                return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
            }

            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_user_show', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/delete.html.twig', ['user' => $user, ]);
    }

}
