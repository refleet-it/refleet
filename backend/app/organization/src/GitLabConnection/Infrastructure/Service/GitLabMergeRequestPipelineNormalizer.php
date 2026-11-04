<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Service;

/**
 * Turns GitLab's merge request resource into the shape of MergeRequestPipelineReaderInterface.
 */
final readonly class GitLabMergeRequestPipelineNormalizer
{
    /**
     * @param array<mixed> $mergeRequest
     *
     * @return array{state: string, headSha: string, pipelineId: ?string, pipelineSha: ?string, pipelineRef: ?string, pipelineStatus: ?string}
     *
     * @throws \UnexpectedValueException when GitLab left out the state or head commit, without which nothing can be decided
     */
    public function normalize(array $mergeRequest): array
    {
        $state = $this->scalar($mergeRequest, 'state');
        $headSha = $this->scalar($mergeRequest, 'sha');

        if (null === $state || null === $headSha) {
            throw new \UnexpectedValueException('unexpected merge request shape from GitLab: missing state or sha');
        }

        $pipeline = \is_array($mergeRequest['head_pipeline'] ?? null) ? $mergeRequest['head_pipeline'] : [];

        return [
            'state' => $state,
            'headSha' => $headSha,
            'pipelineId' => $this->scalar($pipeline, 'id'),
            'pipelineSha' => $this->scalar($pipeline, 'sha'),
            'pipelineRef' => $this->scalar($pipeline, 'ref'),
            'pipelineStatus' => $this->scalar($pipeline, 'status'),
        ];
    }

    /**
     * @param array<mixed> $data
     */
    private function scalar(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return \is_scalar($value) ? (string) $value : null;
    }
}
