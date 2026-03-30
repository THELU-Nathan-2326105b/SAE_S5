<?php

namespace App\Tests\Security;

use App\Security\RateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Tests unitaires pour le service RateLimiter.
 *
 * Couvre les mécanismes de protection contre le brute-force :
 * comptage des tentatives, calcul du TTL, et remise à zéro.
 */
class RateLimiterTest extends TestCase
{
    private CacheInterface $cache;
    private RateLimiter $limiter;

    /**
     * Initialise les doublons (mocks) avant chaque test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->limiter = new RateLimiter($this->cache);
    }

    /**
     * Vérifie qu'un nombre de tentatives inférieur au seuil ne bloque pas l'utilisateur.
     *
     * @return void
     */
    public function testTooManyAttemptsReturnsFalseWhenUnderLimit(): void
    {
        $this->cache
            ->method('get')
            ->willReturnCallback(fn(string $key, callable $cb) => 3);

        $this->assertFalse(
            $this->limiter->tooManyAttempts('login_127_0_0_1', 5, 60),
            'Moins de 5 tentatives ne doit pas déclencher le blocage.'
        );
    }

    /**
     * Vérifie que le blocage s'active exactement au moment où le seuil est atteint.
     *
     * @return void
     */
    public function testTooManyAttemptsReturnsTrueAtLimit(): void
    {
        $this->cache
            ->method('get')
            ->willReturnCallback(fn(string $key, callable $cb) => 5);

        $this->assertTrue(
            $this->limiter->tooManyAttempts('login_127_0_0_1', 5, 60),
            'Exactement 5 tentatives doit déclencher le blocage.'
        );
    }

    /**
     * Vérifie que le blocage reste actif si l'utilisateur dépasse le seuil.
     *
     * @return void
     */
    public function testTooManyAttemptsReturnsTrueWhenOverLimit(): void
    {
        $this->cache
            ->method('get')
            ->willReturnCallback(fn(string $key, callable $cb) => 10);

        $this->assertTrue(
            $this->limiter->tooManyAttempts('login_127_0_0_1', 5, 60),
            'Dépasser le seuil doit maintenir le blocage.'
        );
    }

    /**
     * Vérifie qu'un nouvel utilisateur (sans cache) n'est pas bloqué.
     *
     * @return void
     */
    public function testTooManyAttemptsReturnsFalseWhenNoPreviousAttempts(): void
    {
        $this->cache
            ->method('get')
            ->willReturnCallback(fn(string $key, callable $cb) => $cb($this->createMock(ItemInterface::class)));

        $this->assertFalse(
            $this->limiter->tooManyAttempts('login_new_ip', 5, 60),
            'Sans tentatives préalables, le système ne doit pas bloquer.'
        );
    }

    /**
     * Vérifie le calcul du nombre de tentatives restantes.
     *
     * @return void
     */
    public function testRetriesLeftReturnsCorrectCount(): void
    {
        $this->cache
            ->method('get')
            ->willReturnCallback(fn(string $key, callable $cb) => 2);

        $this->assertSame(3, $this->limiter->retriesLeft('login_127_0_0_1', 5));
    }

    /**
     * Vérifie que le nombre de tentatives restantes ne devient jamais négatif.
     *
     * @return void
     */
    public function testRetriesLeftNeverReturnsNegative(): void
    {
        $this->cache
            ->method('get')
            ->willReturnCallback(fn(string $key, callable $cb) => 10);

        $this->assertSame(0, $this->limiter->retriesLeft('login_127_0_0_1', 5));
    }

    /**
     * Vérifie que le délai avant déblocage (TTL) est calculé correctement.
     *
     * @return void
     */
    public function testAvailableInReturnsFutureSeconds(): void
    {
        $future = time() + 45;

        $this->cache
            ->method('get')
            ->willReturnCallback(fn(string $key, callable $cb) => $future);

        $result = $this->limiter->availableIn('login_127_0_0_1');

        $this->assertGreaterThan(0, $result, 'Doit retourner un délai positif.');
        $this->assertLessThanOrEqual(45, $result);
    }

    /**
     * Vérifie que la purge du limiteur supprime bien la clé et son TTL du cache.
     *
     * @return void
     */
    public function testClearDeletesBothKeys(): void
    {
        $compteurAppels = 0;
        
        $this->cache
            ->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(function (string $key) use (&$compteurAppels) {
                $compteurAppels++;

                if ($compteurAppels === 1) {
                    $this->assertEquals('login_127_0_0_1', $key);
                }
                elseif ($compteurAppels === 2) {
                    $this->assertEquals('login_127_0_0_1_ttl', $key);
                }

                return true;
            });

        $this->limiter->clear('login_127_0_0_1');
    }

    /**
     * Documente un bug existant dans le code métier : le compteur n'est pas incrémenté.
     * Ce test échouera tant que la méthode hit() de RateLimiter ne sera pas corrigée.
     *
     * @return void
     */
    public function testHitIncrementsCounter(): void
    {
        $counter = 0;

        $this->cache
            ->method('get')
            ->willReturnCallback(function (string $key, callable $cb) use (&$counter) {
                if (str_ends_with($key, '_ttl')) {
                    return time() + 60;
                }
                $counter++;
                return $counter;
            });

        $this->cache->method('delete')->willReturn(true);

        $this->limiter->hit('login_127_0_0_1', 60);
        $this->limiter->hit('login_127_0_0_1', 60);

        $this->assertGreaterThanOrEqual(
            2,
            $this->limiter->attempts('login_127_0_0_1'),
            'Le compteur doit être incrémenté à chaque appel de hit().'
        );
    }
}
