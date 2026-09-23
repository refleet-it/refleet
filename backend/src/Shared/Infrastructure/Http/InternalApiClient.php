<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\InternalApiCallFailedException;
use App\Shared\Infrastructure\Security\InternalTokenAuthenticator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin wrapper every HTTP-backed Shared\Domain\Service port adapter calls through to reach
 * another context's container under /internal — see docs/adr/0001-multiple-kernels.md. Each
 * adapter is a ~20-line class that maps its interface's method onto one `get()` call here;
 * this class owns what all of them share: the internal-token header, a short request
 * timeout, and a short-TTL response cache so a port called on nearly every request (e.g.
 * OrganizationContextProviderInterface) doesn't mean a network round trip on every request.
 */
final readonly class InternalApiClient
{
    private const float TIMEOUT_SECONDS = 5.0;

    private const int CACHE_TTL_SECONDS = 5;

    private string $internalServiceToken;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(service: 'cache.app')]
        private CacheInterface $cache,
    ) {
        $token = $_ENV['INTERNAL_SERVICE_TOKEN'] ?? throw new \InvalidArgumentException('INTERNAL_SERVICE_TOKEN environment variable is not set');
        if (!\is_string($token) || '' === $token) {
            throw new \InvalidArgumentException('INTERNAL_SERVICE_TOKEN must be a non-empty string');
        }

        $this->internalServiceToken = $token;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<mixed>
     */
    public function get(string $baseUrl, string $path, array $query = []): array
    {
        $cacheKey = 'internal_api.'.\hash('sha256', $baseUrl.$path.\serialize($query));

        /** @var array<mixed> $result */
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($baseUrl, $path, $query): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            try {
                $response = $this->httpClient->request('GET', \rtrim($baseUrl, '/').$path, [
                    'query' => $query,
                    'headers' => [InternalTokenAuthenticator::HEADER => $this->internalServiceToken],
                    'timeout' => self::TIMEOUT_SECONDS,
                ]);

                /** @var array<mixed> $data */
                $data = $response->toArray();
            } catch (ExceptionInterface $exception) {
                throw new InternalApiCallFailedException($path, $exception);
            }

            return $data;
        });

        return $result;
    }

    /**
     * Uncached, unlike get(): used for calls that carry a secret in the body (e.g. an API
     * key to validate) — repeating the exact same request within the cache window is rare
     * enough that caching it isn't worth holding that secret in the cache backend.
     *
     * @param array<string, mixed> $body
     *
     * @return array<mixed>
     */
    public function post(string $baseUrl, string $path, array $body = []): array
    {
        try {
            $response = $this->httpClient->request('POST', \rtrim($baseUrl, '/').$path, [
                'json' => $body,
                'headers' => [InternalTokenAuthenticator::HEADER => $this->internalServiceToken],
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            /** @var array<mixed> $data */
            $data = $response->toArray();
        } catch (ExceptionInterface $exception) {
            throw new InternalApiCallFailedException($path, $exception);
        }

        return $data;
    }
}
