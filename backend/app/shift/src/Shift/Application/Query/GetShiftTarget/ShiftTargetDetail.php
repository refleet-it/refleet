<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\GetShiftTarget;

/**
 * @SuppressWarnings("PHPMD.ExcessiveParameterList") Flat 1:1 projection of the
 * ShiftTarget aggregate for the detail API response - the field count mirrors the
 * aggregate's own columns, not accidental complexity.
 */
final readonly class ShiftTargetDetail
{
    public function __construct(
        public string $id,
        public string $shiftId,
        public string $organizationId,
        public string $projectId,
        public string $projectSnapshotExternalId,
        public string $projectSnapshotPath,
        public string $projectSnapshotName,
        public ?string $projectSnapshotDefaultBranch,
        public string $status,
        public ?string $changeSummary,
        public ?string $changeBranchName,
        public ?string $runnerJobId,
        public ?string $runnerName,
        public ?string $changeStartedAt,
        public ?string $changeCompletedAt,
        public ?string $mergeRequestUrl,
        public ?string $mergeRequestExternalIid,
        public string $mergeRequestStatus,
        public ?string $mergeRequestUpdatedAt,
        public string $createdAt,
    ) {
    }
}
