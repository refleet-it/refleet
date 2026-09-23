<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\ListShiftTargets;

final readonly class ListShiftTargetsQuery
{
    public function __construct(
        public string $shiftId,
        public string $organizationId,
        public ?string $status = null,
        public ?int $page = null,
        public ?int $limit = null,
    ) {
    }
}
