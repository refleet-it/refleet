<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Service;

use App\Identity\Account\Domain\Account\Exception\OidcAuthenticationFailedException;
use Jose\Component\Core\JWKSet;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The issuer's public signing keys, cached.
 *
 * The cache is short-lived because keys rotate — Dex rolls its signing key every few hours —
 * and `$forceRefresh` exists for the case the TTL cannot cover: a token signed with a key
 * newer than the cached set, which is ordinary rather than suspicious and must not read as a
 * bad signature.
 */
final readonly class OidcJwksProvider
{
    private const int CACHE_SECONDS = 300;

    public function __construct(
        private OidcHttpReader $http,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function keys(string $jwksUri, bool $forceRefresh): JWKSet
    {
        $item = $this->cache->getItem('oidc_jwks_'.\hash('sha256', $jwksUri));
        $cached = $item->get();

        if (!$forceRefresh && $item->isHit() && \is_string($cached)) {
            $keySet = $this->parse($cached);

            if (null !== $keySet) {
                return $keySet;
            }
        }

        $raw = $this->http->getRaw($jwksUri, "the issuer's signing keys could not be read");
        $keySet = $this->parse($raw);

        if (null === $keySet || 0 === $keySet->count()) {
            throw new OidcAuthenticationFailedException('the issuer published no usable signing keys');
        }

        $this->cache->save($item->set($raw)->expiresAfter(self::CACHE_SECONDS));

        return $keySet;
    }

    private function parse(string $raw): ?JWKSet
    {
        try {
            return JWKSet::createFromJson($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
