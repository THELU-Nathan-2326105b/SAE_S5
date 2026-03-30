<?php

namespace App\Tests\Security;

use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Security\LoginAuthenticator;
use App\Security\RateLimiter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour le processus d'authentification (LoginAuthenticator).
 *
 * Vérifie le comportement du login, l'intégration de reCAPTCHA,
 * et la vérification des premières connexions.
 */
class LoginAuthenticatorTest extends TestCase
{
    private RouterInterface $router;
    private UsersRepository $usersRepository;
    private HttpClientInterface $httpClient;
    private RateLimiter $rateLimiter;
    private EntityManagerInterface $em;
    private LoginAuthenticator $authenticator;

    /**
     * Initialise les services nécessaires à l'authentificateur.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->router          = $this->createMock(RouterInterface::class);
        $this->usersRepository = $this->createMock(UsersRepository::class);
        $this->httpClient      = $this->createMock(HttpClientInterface::class);
        $this->rateLimiter     = $this->createMock(RateLimiter::class);
        $this->em              = $this->createMock(EntityManagerInterface::class);

        $this->authenticator = new LoginAuthenticator(
            $this->router,
            $this->usersRepository,
            $this->httpClient,
            $this->rateLimiter,
            $this->em
        );
    }

    /**
     * Vérifie que le gestionnaire intercepte bien les requêtes POST sur /login-handler.
     *
     * @return void
     */
    public function testSupportsReturnsTrueForLoginHandlerPost(): void
    {
        $request = Request::create('/login-handler', 'POST');
        $this->assertTrue($this->authenticator->supports($request));
    }

    /**
     * Vérifie que le gestionnaire ignore les requêtes GET.
     *
     * @return void
     */
    public function testSupportsReturnsFalseForGetRequest(): void
    {
        $request = Request::create('/login-handler', 'GET');
        $this->assertFalse($this->authenticator->supports($request));
    }

    /**
     * Vérifie qu'une adresse IP bloquée est rejetée avec le délai restant.
     *
     * @return void
     */
    public function testAuthenticateThrowsWhenRateLimited(): void
    {
        $this->rateLimiter->method('tooManyAttempts')->willReturn(true);
        $this->rateLimiter->method('availableIn')->willReturn(42);

        $request = $this->buildLoginRequest('victim@test.com', 'password');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessageMatches('/42 secondes/');

        $this->authenticator->authenticate($request);
    }

    /**
     * Vérifie qu'un score reCAPTCHA insuffisant provoque un rejet de l'authentification.
     *
     * @return void
     */
    public function testAuthenticateThrowsWhenRecaptchaScoreTooLow(): void
    {
        $this->rateLimiter->method('tooManyAttempts')->willReturn(false);

        $user = $this->createMock(Users::class);
        $user->method('isUserFirstconnexion')->willReturn(false);
        $this->usersRepository->method('findOneBy')->willReturn($user);

        $this->mockRecaptchaResponse(['success' => true, 'score' => 0.2, 'action' => 'login']);

        $request = $this->buildLoginRequest('user@test.com', 'pass', 'low_score_token');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessageMatches('/reCAPTCHA/i');

        $this->authenticator->authenticate($request);
    }

    /**
     * Vérifie que le message d'erreur d'authentification reste générique pour ne pas fuiter de données.
     *
     * @return void
     */
    public function testOnAuthenticationFailureStoresGenericErrorMessage(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/login-handler', 'POST');
        $request->setSession($session);

        $this->router->method('generate')->willReturn('/login');

        $exception = new AuthenticationException('Email ou mot de passe incorrect.');
        $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertSame(
            'Email ou mot de passe incorrect.',
            $session->get('login_error'),
            'Le message stocké en session doit rester générique'
        );
    }

    /**
     * Construit une fausse requête HTTP pour simuler la soumission du formulaire de connexion.
     *
     * @param string $email
     * @param string $password
     * @param string $recaptchaToken
     * @param string $ip
     *
     * @return Request
     */
    private function buildLoginRequest(
        string $email,
        string $password,
        string $recaptchaToken = 'dummy_token',
        string $ip = '127.0.0.1'
    ): Request {
        $request = Request::create('/login-handler', 'POST',[
            'email'               => $email,
            'password'            => $password,
            'g-recaptcha-response' => $recaptchaToken,
        ]);
        $request->server->set('REMOTE_ADDR', $ip);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        return $request;
    }

    /**
     * Simule la réponse de l'API Google reCAPTCHA.
     *
     * @param array $payload
     *
     * @return void
     */
    private function mockRecaptchaResponse(array $payload): void
    {
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn($payload);

        $this->httpClient
            ->method('request')
            ->willReturn($httpResponse);
    }
}
