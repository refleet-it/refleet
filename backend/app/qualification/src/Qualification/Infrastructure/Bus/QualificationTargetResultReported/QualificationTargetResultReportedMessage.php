<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Bus\QualificationTargetResultReported;

/**
 * Reports a finished qualification job back to the Qualification context.
 *
 * Every context on this route keeps its own copy of this class; they agree on the
 * wire contract only, never on an import.
 */
final readonly class QualificationTargetResultReportedMessage
{
    public function __construct(
        public string $qualificationId,
        public string $targetId,
        public string $organizationId,
        public bool $success,
        public string $summary,
        public string $runnerName,
        public ?string $errorMessage,
        public ?int $score,
    ) {
    }
}
