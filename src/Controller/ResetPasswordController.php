<?php

namespace App\Controller;

use App\Entity\Users;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * ResetPasswordController
 * 
 * Contrôleur responsable de la gestion de la réinitialisation de mot de passe.
 * Utilise le bundle SymfonyCasts pour la gestion sécurisée des tokens.
 * 
 * @package App\Controller
 */
#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    /**
     * Constructeur du contrôleur de réinitialisation
     * 
     * @param ResetPasswordHelperInterface $resetPasswordHelper Service de réinitialisation
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine
     */
    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Affiche et traite le formulaire de demande de réinitialisation
     * Envoie un email avec un lien de réinitialisation
     * 
     * @param Request $request Requête HTTP
     * @param MailerInterface $mailer Service pour envoyer les emails
     * @param TranslatorInterface $translator Service de traduction
     * @return Response Formulaire de demande ou page de confirmation
     */
    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request, MailerInterface $mailer, TranslatorInterface $translator): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $email */
            $email = $form->get('user_email')->getData();

            return $this->processSendingPasswordResetEmail($email, $mailer, $translator);
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    /**
     * Page de confirmation après demande de réinitialisation
     * 
     * @return Response Page de confirmation
     */
    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        // Génère un faux token si l'utilisateur n'existe pas
        // Cela évite d'exposer si un utilisateur est inscrit ou non
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    /**
     * Valide et traite la réinitialisation du mot de passe
     * 
     * @param Request $request Requête HTTP
     * @param UserPasswordHasherInterface $passwordHasher Service de hashage des mots de passe
     * @param TranslatorInterface $translator Service de traduction
     * @param ?string $token Token de réinitialisation optionnel
     * @return Response Formulaire de réinitialisation ou redirection
     */
    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, TranslatorInterface $translator, ?string $token = null): Response
    {
        if ($token) {
            // Stocke le token en session et le retire de l'URL
            // Évite que le token soit exposé via l'URL à des tiers
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();

        if (null === $token) {
            throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
        }

        try {
            /** @var Users $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error', sprintf(
                '%s - %s',
                $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE, [], 'ResetPasswordBundle'),
                $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
            ));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        // Token valide ; permettre à l'utilisateur de changer son mot de passe
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Un token de réinitialisation ne doit être utilisé qu'une fois, le supprimer
            $this->resetPasswordHelper->removeResetRequest($token);

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Encode (hash) le mot de passe en clair et le définir
            $user->setUserPwd($passwordHasher->hashPassword($user, $plainPassword));
            $this->entityManager->flush();

            // La session est nettoyée après le changement de mot de passe
            $this->cleanSessionAfterReset();

            return $this->redirectToRoute('login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }

    /**
     * Traite l'envoi de l'email de réinitialisation
     * Génère un token et envoie un email avec le lien de réinitialisation
     * 
     * @param string $emailFormData Email saisi dans le formulaire
     * @param MailerInterface $mailer Service pour envoyer les emails
     * @param TranslatorInterface $translator Service de traduction
     * @return RedirectResponse Redirection vers la page de confirmation
     */
    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer, TranslatorInterface $translator): RedirectResponse
    {
        $user = $this->entityManager->getRepository(Users::class)->findOneBy([
            'user_email' => $emailFormData,
        ]);

        // Ne pas révéler si un compte utilisateur a été trouvé ou non
        if (!$user) {
            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            // Si vous voulez informer l'utilisateur pourquoi l'email n'a pas été envoyé,
            // décommentez les lignes ci-dessous
            return $this->redirectToRoute('app_check_email');
        }

        $email = (new TemplatedEmail())
            ->from(new Address('thelu@alwaysdata.net', 'Thelu'))
            ->to((string) $user->getUserEmail())
            ->subject('Your password reset request')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
            ])
        ;

        $mailer->send($email);

        // Stocke l'objet token en session pour utilisation dans la route check-email
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }
}
