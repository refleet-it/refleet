<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\ListQualifications;

final readonly class ListQualificationsQuery
{
    public function __construct(
        public string $organizationId,
        public ?int $page = null,
        public ?int $limit = null,
        public ?string $sortBy = null,
        public ?string $sortDirection = null,
        public ?string $search = null,
        public ?string $status = null,
        public bool $archived = false,
    ) {
    }
}
