<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\GetQualification;

/**
 * @SuppressWarnings("PHPMD.ExcessiveParameterList") Flat 1:1 projection of the
 * Qualification aggregate for the detail API response - the field count mirrors the
 * aggregate's own columns, not accidental complexity.
 */
final readonly class QualificationDetail
{
    /**
     * @param array<string, int>                                                 $statusBreakdown
     * @param list<array{id: string, name: string, kind: string, builtIn: bool}> $qualificationSources
     */
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $title,
        public ?string $description,
        public string $createdBy,
        public string $status,
        public string $qualificationMode,
        public ?string $qualificationEngine,
        public string $qualificationPrompt,
        public ?string $qualificationModel,
        public ?string $qualificationRules,
        public array $qualificationSources,
        public ?string $cancelReason,
        public int $targetCount,
        public array $statusBreakdown,
        public ?int $progressPercent,
        public string $createdAt,
        public ?string $startedAt,
        public ?string $completedAt,
        public ?string $cancelledAt,
        public ?string $archivedAt,
    ) {
    }
}
