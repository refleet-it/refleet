<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Query\ListRunnerJobs;

final readonly class ListRunnerJobsQuery
{
    public function __construct(
        public string $runnerId,
        public string $organizationId,
        public ?int $page = null,
        public ?int $limit = null,
    ) {
    }
}
