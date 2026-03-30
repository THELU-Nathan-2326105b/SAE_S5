<?php

/**
 * Service de limitation de tentatives (Rate Limiting)
 *
 * Gére les tentatives de connexion pour prévenir les attaques par force brute
 * Utilise un système de cache avec TTL
 *
 * @package App\Security
 */

namespace App\Security;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Class RateLimiter
 *
 * Service de limitation de débit pour sécuriser les opérations sensibles.
 */
class RateLimiter
{
    /**
     * Constructeur du service de rate limiting
     *
     * @param CacheInterface $cache
     */
    public function __construct(private CacheInterface $cache)
    {
    }

    /**
     * Enregistre une tentative pour une clé donnée
     *
     * @param string $key Clé unique pour l'action
     * @param int $decaySeconds Durée d'expiration en secondes
     * @return void
     */
    public function hit(string $key, int $decaySeconds): void
    {
        $this->cache->get($key, function (ItemInterface $item) use ($decaySeconds) {
            $item->expiresAfter($decaySeconds);
            return 1;
        });

        $this->cache->delete($key . '_ttl');
        $this->cache->get($key . '_ttl', function (ItemInterface $item) use ($decaySeconds) {
            $item->expiresAfter($decaySeconds);
            return time() + $decaySeconds;
        });
    }

    /**
     * Récupère le nombre de tentatives pour une clé
     *
     * @param string $key
     * @return int Nombre de tentatives
     */
    public function attempts(string $key): int
    {
        return $this->cache->get($key, fn () => 0);
    }

    /**
     * Vérifie si le nombre de tentatives a été dépassé
     *
     * @param string $key
     * @param int $maxAttempts Nombre maximal de tentatives autorisées
     * @param int $decaySeconds Durée d'expiration
     * @return bool
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    /**
     * Récupère le nombre de tentatives restantes
     *
     * @param string $key
     * @param int $maxAttempts Nombre maximal de tentatives
     * @return int Tentatives restantes (0 minimum)
     */
    public function retriesLeft(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($key));
    }

    /**
     * Récupère le temps d'attente avant la prochaine tentative
     *
     * @param string $key
     * @return int Secondes d'attente (0 minimum)
     */
    public function availableIn(string $key): int
    {
        return max(0, ($this->cache->get($key . '_ttl', fn () => time()) - time()));
    }

    /**
     * Supprime les données de rate limiting pour une clé
     *
     * @param string $key
     * @return void
     */
    public function clear(string $key): void
    {
        $this->cache->delete($key);
        $this->cache->delete($key . '_ttl');
    }
}
