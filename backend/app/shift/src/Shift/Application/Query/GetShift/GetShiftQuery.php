<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\GetShift;

final readonly class GetShiftQuery
{
    public function __construct(
        public string $shiftId,
        public string $organizationId,
    ) {
    }
}
