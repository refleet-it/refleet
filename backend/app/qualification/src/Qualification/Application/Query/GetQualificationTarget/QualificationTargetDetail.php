<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\GetQualificationTarget;

final readonly class QualificationTargetDetail
{
    public function __construct(
        public string $id,
        public string $qualificationId,
        public string $organizationId,
        public string $projectId,
        public string $projectSnapshotExternalId,
        public string $projectSnapshotPath,
        public string $projectSnapshotName,
        public ?string $projectSnapshotDefaultBranch,
        public string $status,
        public ?string $summary,
        public ?int $score,
        public bool $overridden,
        public ?string $overrideNote,
        public ?string $runnerJobId,
        public ?string $runnerName,
        public ?string $startedAt,
        public ?string $completedAt,
        public string $createdAt,
    ) {
    }
}
