<?php

namespace App\Security;

use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LoginAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private RouterInterface $router,
        private UsersRepository $usersRepository,
        private HttpClientInterface $client,
        private RateLimiter $limiter,
        private EntityManagerInterface $em 
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST')
            && $request->getPathInfo() === '/login-handler';
    }

    public function authenticate(Request $request): Passport
    {
        $ip = $request->getClientIp() ?? 'unknown';
        $key = 'login_' . str_replace(':', '_', $ip);

        // 5 tentatives max / 15 minutes
        if ($this->limiter->tooManyAttempts($key, 5, 900)) {
            $seconds = $this->limiter->availableIn($key);

            throw new AuthenticationException(
                "Trop de tentatives. Réessayez dans {$seconds} secondes."
            );
        }

        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $recaptchaResponse = $request->request->get('g-recaptcha-response');

        // Vérification reCAPTCHA
        $secretKey = '6LeygQAsAAAAAK-6I0IrVAZGjUk02p4Iw5oguPHq';

        $response = $this->client->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $secretKey,
                'response' => $recaptchaResponse,
            ],
        ]);

        $result = $response->toArray();

        if (
            !$result['success'] ||
            ($result['score'] ?? 0) < 0.5 ||
            ($result['action'] ?? '') !== 'login'
        ) {
            throw new AuthenticationException('Vérification reCAPTCHA échouée.');
        }

        return new Passport(
            new UserBadge($email, function ($userIdentifier) {
                return $this->usersRepository->findOneBy(['user_email' => $userIdentifier]);
            }),
            new PasswordCredentials($password)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Mise à jour de la date de dernière connexion
        $user = $token->getUser();
        if ($user instanceof \App\Entity\Users) {
            $user->setUserLastconnexion(new \DateTimeImmutable('today'));
            $this->em->flush();
        }

        return new RedirectResponse($this->router->generate('home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set('login_error', 'Email ou mot de passe incorrect.');
        return new RedirectResponse($this->router->generate('login'));
    }
}