<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Command\RecordQualificationTargetResult;

use App\Shared\Application\Command\Sync\CommandInterface;

/**
 * Dispatched by the Runner context's ReportRunnerJobResultHandler once a
 * kind=qualification job settles. `score` is the agent's 1–5 verdict and is required
 * on a success — a successful run that carries none is recorded as a failure, since
 * there is no decision to record.
 */
final readonly class RecordQualificationTargetResultCommand implements CommandInterface
{
    public function __construct(
        public string $qualificationId,
        public string $targetId,
        public string $organizationId,
        public bool $success,
        public string $summary,
        public string $runnerName,
        public ?string $errorMessage = null,
        public ?int $score = null,
    ) {
    }
}
