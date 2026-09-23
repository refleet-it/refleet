<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\ListShifts;

final readonly class ShiftOverview
{
    /**
     * @param array<string, int> $statusBreakdown
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public string $status,
        public ?string $qualificationId,
        public ?string $changeMode,
        public int $targetCount,
        public int $terminalTargetCount,
        public array $statusBreakdown,
        public ?int $progressPercent,
        public string $createdAt,
        public ?string $archivedAt,
    ) {
    }
}
