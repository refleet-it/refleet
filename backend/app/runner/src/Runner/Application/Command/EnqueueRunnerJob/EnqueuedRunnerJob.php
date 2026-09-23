<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\EnqueueRunnerJob;

final readonly class EnqueuedRunnerJob
{
    public function __construct(
        public string $jobId,
    ) {
    }
}
