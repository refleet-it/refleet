<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\ListQualificationTargets;

/**
 * Carries the full project snapshot (not just name/path) because this DTO doubles as
 * the payload Shift's CreateQualificationHandler consumes over the bus to build
 * ShiftTargets without re-querying the Project context.
 */
final readonly class QualificationTargetOverview
{
    public function __construct(
        public string $id,
        public string $qualificationId,
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
        public ?string $runnerName,
        public string $createdAt,
    ) {
    }
}
