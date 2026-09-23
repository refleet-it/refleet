<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Query\ListRunners;

final readonly class ListRunnersQuery
{
    public function __construct(
        public string $organizationId,
        public ?int $page = null,
        public ?int $limit = null,
        public ?string $sortBy = null,
        public ?string $sortDirection = null,
        public bool $archived = false,
    ) {
    }
}
