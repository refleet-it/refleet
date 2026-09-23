<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Query\ListRunnerJobs;

final readonly class RunnerJobOverview
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $mode,
        public string $status,
        public string $ownerId,
        public string $ownerLabel,
        public string $ownerTargetId,
        public string $projectName,
        public int $attemptCount,
        public ?string $resultSummary,
        public ?string $errorMessage,
        public string $createdAt,
        public ?string $claimedAt,
        public ?string $completedAt,
    ) {
    }
}
