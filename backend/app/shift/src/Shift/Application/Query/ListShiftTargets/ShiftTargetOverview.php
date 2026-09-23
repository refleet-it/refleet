<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\ListShiftTargets;

final readonly class ShiftTargetOverview
{
    public function __construct(
        public string $id,
        public string $shiftId,
        public string $projectId,
        public string $projectName,
        public string $projectPath,
        public string $status,
        public ?string $changeSummary,
        public ?string $runnerName,
        public ?string $mergeRequestUrl,
        public string $mergeRequestStatus,
        public string $createdAt,
    ) {
    }
}
