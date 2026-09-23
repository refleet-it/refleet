<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Query\GetAvailableModels;

final readonly class GetAvailableModelsQuery
{
    public function __construct(
        public string $organizationId,
    ) {
    }
}
