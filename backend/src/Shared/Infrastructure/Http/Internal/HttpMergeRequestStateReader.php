<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Internal;

use App\Shared\Domain\Service\MergeRequestStateReaderInterface;
use App\Shared\Infrastructure\Http\InternalApiClient;

/**
 * MergeRequestStateReaderInterface adapter for every context but Organization, which
 * implements it directly. See docs/adr/0001-multiple-kernels.md.
 */
final readonly class HttpMergeRequestStateReader implements MergeRequestStateReaderInterface
{
    private string $organizationInternalUrl;

    public function __construct(
        private InternalApiClient $client,
    ) {
        $url = $_ENV['ORGANIZATION_INTERNAL_URL'] ?? throw new \InvalidArgumentException('ORGANIZATION_INTERNAL_URL environment variable is not set');
        if (!\is_string($url) || '' === $url) {
            throw new \InvalidArgumentException('ORGANIZATION_INTERNAL_URL must be a non-empty string');
        }

        $this->organizationInternalUrl = $url;
    }

    #[\Override]
    public function statesFor(string $organizationId, array $iidsByProjectExternalId): array
    {
        $data = $this->client->post(
            $this->organizationInternalUrl,
            '/internal/gitlab/merge-request-states/'.$organizationId,
            ['iidsByProject' => $iidsByProjectExternalId],
        );

        $states = $data['states'] ?? null;

        return \is_array($states) ? $this->normalize($states) : [];
    }

    /**
     * @param array<mixed> $states
     *
     * @return array<string, array<string, string>>
     */
    private function normalize(array $states): array
    {
        $normalized = [];

        foreach ($states as $projectExternalId => $byIid) {
            if (!\is_array($byIid)) {
                continue;
            }

            foreach ($byIid as $iid => $state) {
                if (\is_string($state)) {
                    $normalized[(string) $projectExternalId][(string) $iid] = $state;
                }
            }
        }

        return $normalized;
    }
}
