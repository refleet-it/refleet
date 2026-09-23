<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\ReportRunnerJobResult;

final readonly class ReportedRunnerJobResult
{
    /**
     * @param array<string, mixed>|null $resultDetails
     */
    public function __construct(
        public string $jobId,
        public string $status,
        public ?string $resultSummary,
        public ?array $resultDetails,
        public ?string $errorMessage,
        public ?string $completedAt,
    ) {
    }
}
