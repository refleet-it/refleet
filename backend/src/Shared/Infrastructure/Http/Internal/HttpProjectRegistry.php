<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Internal;

use App\Shared\Domain\Service\ProjectRegistryInterface;
use App\Shared\Infrastructure\Http\InternalApiClient;

/**
 * ProjectRegistryInterface adapter for every context but Project, which implements it
 * directly. See docs/adr/0001-multiple-kernels.md.
 */
final readonly class HttpProjectRegistry implements ProjectRegistryInterface
{
    private string $projectInternalUrl;

    public function __construct(
        private InternalApiClient $client,
    ) {
        $url = $_ENV['PROJECT_INTERNAL_URL'] ?? throw new \InvalidArgumentException('PROJECT_INTERNAL_URL environment variable is not set');
        if (!\is_string($url) || '' === $url) {
            throw new \InvalidArgumentException('PROJECT_INTERNAL_URL must be a non-empty string');
        }

        $this->projectInternalUrl = $url;
    }

    #[\Override]
    public function register(
        string $organizationId,
        string $externalId,
        string $name,
        string $path,
        ?string $webUrl = null,
        ?string $defaultBranch = null,
        ?string $description = null,
    ): void {
        $this->client->post($this->projectInternalUrl, '/internal/projects/register', [
            'organizationId' => $organizationId,
            'externalId' => $externalId,
            'name' => $name,
            'path' => $path,
            'webUrl' => $webUrl,
            'defaultBranch' => $defaultBranch,
            'description' => $description,
        ]);
    }

    #[\Override]
    public function archiveMissing(string $organizationId, array $seenExternalIds): int
    {
        /** @var array{archivedCount: int} $data */
        $data = $this->client->post($this->projectInternalUrl, '/internal/projects/archive-missing', [
            'organizationId' => $organizationId,
            'seenExternalIds' => $seenExternalIds,
        ]);

        return $data['archivedCount'];
    }
}
