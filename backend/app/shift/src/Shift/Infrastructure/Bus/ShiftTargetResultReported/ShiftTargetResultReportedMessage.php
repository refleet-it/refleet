<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Bus\ShiftTargetResultReported;

/**
 * Reports a finished change job back to the Shift context.
 *
 * Every context on this route keeps its own copy of this class; they agree on the
 * wire contract only, never on an import.
 */
final readonly class ShiftTargetResultReportedMessage
{
    public function __construct(
        public string $shiftId,
        public string $targetId,
        public string $organizationId,
        public bool $success,
        public string $summary,
        public string $runnerName,
        public ?string $errorMessage,
        public ?string $branchName,
        public ?string $mergeRequestUrl = null,
        public ?string $mergeRequestIid = null,
    ) {
    }
}
