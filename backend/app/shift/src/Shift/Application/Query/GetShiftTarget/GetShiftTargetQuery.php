<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\GetShiftTarget;

final readonly class GetShiftTargetQuery
{
    public function __construct(
        public string $shiftTargetId,
        public string $shiftId,
        public string $organizationId,
    ) {
    }
}
