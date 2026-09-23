<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Service;

use App\Runner\Runner\Domain\Runner\Service\LatestRunnerVersionProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads the `latest` dist-tag of @refleet-it/runner from the npm registry — the one place
 * every published runner version lands (ci/jobs/quality-runner.yml). Cached, because every
 * heartbeat of every runner asks; a failed lookup is cached too, briefly, so an unreachable
 * registry costs one request a minute rather than one per heartbeat.
 */
final readonly class NpmLatestRunnerVersionProvider implements LatestRunnerVersionProviderInterface
{
    private const string CACHE_KEY = 'runner.latest_version';

    private const int TTL_SECONDS = 600;

    private const int FAILURE_TTL_SECONDS = 60;

    private const float TIMEOUT_SECONDS = 3.0;

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        #[Autowire('%env(RUNNER_RELEASE_URL)%')]
        private string $releaseUrl,
    ) {
    }

    #[\Override]
    public function latestVersion(): ?string
    {
        if ('' === $this->releaseUrl) {
            return null;
        }

        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): ?string {
            $version = $this->fetch();
            $item->expiresAfter(null === $version ? self::FAILURE_TTL_SECONDS : self::TTL_SECONDS);

            return $version;
        });
    }

    private function fetch(): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $this->releaseUrl, [
                'headers' => ['Accept' => 'application/json'],
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $version = $response->toArray(false)['version'] ?? null;
        } catch (\Throwable) {
            return null;
        }

        return \is_string($version) && '' !== $version ? $version : null;
    }
}
