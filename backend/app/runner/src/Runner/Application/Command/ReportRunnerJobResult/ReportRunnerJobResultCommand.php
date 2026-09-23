<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\ReportRunnerJobResult;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ReportRunnerJobResultCommand implements CommandInterface
{
    /**
     * @param array<string, mixed>|null $details
     */
    public function __construct(
        public string $jobId,
        public string $runnerId,
        public string $organizationId,
        public string $outcome,
        public string $summary,
        public ?array $details = null,
        public ?string $errorMessage = null,
        public ?int $score = null,
        public ?string $branchName = null,
        public ?string $mergeRequestUrl = null,
        public ?string $mergeRequestIid = null,
    ) {
    }
}
