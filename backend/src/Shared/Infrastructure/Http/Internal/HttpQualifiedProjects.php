<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Internal;

use App\Shared\Domain\Service\QualifiedProjectsInterface;
use App\Shared\Domain\ValueObject\ProjectCatalogEntry;
use App\Shared\Infrastructure\Http\InternalApiClient;

/**
 * QualifiedProjectsInterface adapter for every context but Qualification, which implements
 * it directly. See docs/adr/0001-multiple-kernels.md.
 */
final readonly class HttpQualifiedProjects implements QualifiedProjectsInterface
{
    private string $qualificationInternalUrl;

    public function __construct(
        private InternalApiClient $client,
    ) {
        $url = $_ENV['QUALIFICATION_INTERNAL_URL'] ?? throw new \InvalidArgumentException('QUALIFICATION_INTERNAL_URL environment variable is not set');
        if (!\is_string($url) || '' === $url) {
            throw new \InvalidArgumentException('QUALIFICATION_INTERNAL_URL must be a non-empty string');
        }

        $this->qualificationInternalUrl = $url;
    }

    #[\Override]
    public function forQualification(string $organizationId, string $qualificationId, ?array $projectIds = null): array
    {
        $query = null !== $projectIds ? ['filtered' => '1', 'projectIds' => $projectIds] : [];

        /** @var array{entries: list<array{id: string, externalId: string, name: string, path: string, defaultBranch: ?string}>} $data */
        $data = $this->client->get(
            $this->qualificationInternalUrl,
            '/internal/qualified-projects/'.$organizationId.'/'.$qualificationId,
            $query
        );

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
