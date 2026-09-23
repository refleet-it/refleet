<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Internal;

use App\Shared\Domain\Service\ProjectCatalogInterface;
use App\Shared\Domain\ValueObject\ProjectCatalogEntry;
use App\Shared\Infrastructure\Http\InternalApiClient;

/**
 * ProjectCatalogInterface adapter for every context but Project, which implements it
 * directly. See docs/adr/0001-multiple-kernels.md.
 */
final readonly class HttpProjectCatalog implements ProjectCatalogInterface
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
    public function allForOrganization(string $organizationId): array
    {
        /** @var array{entries: list<array{id: string, externalId: string, name: string, path: string, defaultBranch: ?string}>} $data */
        $data = $this->client->get($this->projectInternalUrl, '/internal/projects/'.$organizationId);

        return $this->toEntries($data);
    }

    #[\Override]
    public function byIds(string $organizationId, array $projectIds): array
    {
        /** @var array{entries: list<array{id: string, externalId: string, name: string, path: string, defaultBranch: ?string}>} $data */
        $data = $this->client->get($this->projectInternalUrl, '/internal/projects/'.$organizationId, [
            'filtered' => '1',
            'projectIds' => $projectIds,
        ]);

        return $this->toEntries($data);
    }

    /**
     * @param array{entries: list<array{id: string, externalId: string, name: string, path: string, defaultBranch: ?string}>} $data
     *
     * @return ProjectCatalogEntry[]
     */
    private function toEntries(array $data): array
    {
        $entries = $data['entries'];

        return \array_map(
            static fn (array $entry): ProjectCatalogEntry => new ProjectCatalogEntry(
                id: $entry['id'],
                externalId: $entry['externalId'],
                name: $entry['name'],
                path: $entry['path'],
                defaultBranch: $entry['defaultBranch'],
            ),
            $entries
        );
    }
}
