<?php

namespace App\Security;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RateLimiter
{
    public function __construct(private CacheInterface $cache)
    {
    }

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

    public function attempts(string $key): int
    {
        return $this->cache->get($key, fn () => 0);
    }

    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    public function retriesLeft(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($key));
    }

    public function availableIn(string $key): int
    {
        return max(0, ($this->cache->get($key . '_ttl', fn () => time()) - time()));
    }

    public function clear(string $key): void
    {
        $this->cache->delete($key);
        $this->cache->delete($key . '_ttl');
    }
}
