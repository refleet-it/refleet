<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\RecordShiftTargetResult;

use App\Shared\Application\Command\Sync\CommandInterface;

/**
 * Dispatched by the Runner context's ReportRunnerJobResultHandler once a kind=change
 * job settles. `branchName` and the merge request fields are only meaningful when
 * `success` is true; a success without a merge request means the agent changed nothing.
 * `pipelineFixJobId` marks the result of a kind=pipeline_fix job instead, which settles
 * only the target's pipeline and leaves the change it already reported alone.
 */
final readonly class RecordShiftTargetResultCommand implements CommandInterface
{
    public function __construct(
        public string $shiftId,
        public string $targetId,
        public string $organizationId,
        public bool $success,
        public string $summary,
        public string $runnerName,
        public ?string $errorMessage = null,
        public ?string $branchName = null,
        public ?string $mergeRequestUrl = null,
        public ?string $mergeRequestIid = null,
        public ?string $headSha = null,
        public ?string $pipelineFixJobId = null,
        public ?string $pipelineFixOutcome = null,
    ) {
    }
}
