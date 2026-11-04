<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\ShiftTarget\ValueObject;

/**
 * What GitLab reports about the head of a merge request at one moment.
 */
final readonly class MergeRequestPipeline
{
    public function __construct(
        public string $headSha,
        public ?string $pipelineId = null,
        public ?string $pipelineSha = null,
        public ?string $pipelineRef = null,
        public ?string $pipelineStatus = null,
    ) {
    }

    /**
     * Merged results pipelines run on a temporary merge commit, so their SHA never equals
     * the branch's. For those the ref is what says the pipeline is the merge request's own,
     * and the caller rules out one it has already acted on by id.
     */
    public function isOfHead(): bool
    {
        return null !== $this->pipelineId
            && ($this->pipelineSha === $this->headSha || $this->isMergedResult());
    }

    private function isMergedResult(): bool
    {
        return 1 === \preg_match('#^refs/merge-requests/\d+/(merge|train)$#', $this->pipelineRef ?? '');
    }
}
