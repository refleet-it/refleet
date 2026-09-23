<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\ListQualifications;

final readonly class QualificationOverview
{
    /**
     * @param array<string, int> $statusBreakdown
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public string $status,
        public string $qualificationMode,
        public int $targetCount,
        public int $terminalTargetCount,
        public array $statusBreakdown,
        public ?int $progressPercent,
        public string $createdAt,
        public ?string $archivedAt,
    ) {
    }
}
