<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\GetShift;

/**
 * @SuppressWarnings("PHPMD.ExcessiveParameterList") Flat 1:1 projection of the
 * Shift aggregate for the detail API response - the field count mirrors the
 * aggregate's own columns, not accidental complexity.
 */
final readonly class ShiftDetail
{
    /**
     * @param array<string, int>                                                 $statusBreakdown
     * @param list<array{id: string, name: string, kind: string, builtIn: bool}> $changeSources
     */
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $title,
        public ?string $description,
        public string $createdBy,
        public string $status,
        public ?string $qualificationId,
        public ?string $changeMode,
        public ?string $changeEngine,
        public ?string $changePrompt,
        public ?string $changeModel,
        public ?string $changeRules,
        public array $changeSources,
        public ?string $cancelReason,
        public int $targetCount,
        public array $statusBreakdown,
        public ?int $progressPercent,
        public string $createdAt,
        public ?string $changeStartedAt,
        public ?string $completedAt,
        public ?string $cancelledAt,
        public ?string $archivedAt,
    ) {
    }
}
