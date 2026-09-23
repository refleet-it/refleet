<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Query\GetRunner;

final readonly class GetRunnerQuery
{
    public function __construct(
        public string $runnerId,
        public string $organizationId,
    ) {
    }
}
