<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\ListQualificationTargetsPage;

final readonly class ListQualificationTargetsPageQuery
{
    public function __construct(
        public string $qualificationId,
        public string $organizationId,
        public ?string $status = null,
        public ?int $page = null,
        public ?int $limit = null,
    ) {
    }
}
