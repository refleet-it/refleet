<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\Exception\TooManyRequestsException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RateLimiter
{
    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @throws TooManyRequestsException when more than $maxAttempts hits are recorded for $key within $windowSeconds
     */
    public function throttle(string $key, int $maxAttempts, int $windowSeconds): void
    {
        $item = $this->cache->getItem($key);
        $storedValue = $item->get();
        $attempts = ($item->isHit() && \is_int($storedValue) ? $storedValue : 0) + 1;

        if ($attempts > $maxAttempts) {
            throw new TooManyRequestsException($windowSeconds);
        }

        $item->set($attempts)->expiresAfter($windowSeconds);
        $this->cache->save($item);
    }

    public static function keyForIp(string $prefix, string $ip): string
    {
        return $prefix.'_'.\hash('sha256', $ip);
    }
}
