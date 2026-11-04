<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Internal;

use App\Shared\Domain\Service\MergeRequestPipelineReaderInterface;
use App\Shared\Infrastructure\Http\InternalApiClient;

/**
 * MergeRequestPipelineReaderInterface adapter for every context but Organization, which
 * implements it directly. See docs/adr/0001-multiple-kernels.md.
 */
final readonly class HttpMergeRequestPipelineReader implements MergeRequestPipelineReaderInterface
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
    public function pipelinesFor(string $organizationId, array $iidsByProjectExternalId): array
    {
        $data = $this->client->post(
            $this->organizationInternalUrl,
            '/internal/gitlab/merge-request-pipelines/'.$organizationId,
            ['iidsByProject' => $iidsByProjectExternalId],
        );

        $pipelines = $data['pipelines'] ?? null;

        return \is_array($pipelines) ? $this->normalize($pipelines) : [];
    }

    /**
     * @param array<mixed> $pipelines
     *
     * @return array<string, array<string, array{state: string, headSha: string, pipelineId: ?string, pipelineSha: ?string, pipelineRef: ?string, pipelineStatus: ?string}>>
     */
    private function normalize(array $pipelines): array
    {
        $normalized = [];

        foreach ($pipelines as $projectExternalId => $byIid) {
            if (!\is_array($byIid)) {
                continue;
            }

            foreach ($byIid as $iid => $entry) {
                if (!\is_array($entry) || !\is_string($entry['state'] ?? null) || !\is_string($entry['headSha'] ?? null)) {
                    continue;
                }

                $normalized[(string) $projectExternalId][(string) $iid] = [
                    'state' => $entry['state'],
                    'headSha' => $entry['headSha'],
                    'pipelineId' => $this->stringOrNull($entry['pipelineId'] ?? null),
                    'pipelineSha' => $this->stringOrNull($entry['pipelineSha'] ?? null),
                    'pipelineRef' => $this->stringOrNull($entry['pipelineRef'] ?? null),
                    'pipelineStatus' => $this->stringOrNull($entry['pipelineStatus'] ?? null),
                ];
            }
        }

        return $normalized;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }
}
