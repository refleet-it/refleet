<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\StartShiftChange;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class StartShiftChangeCommand implements CommandInterface
{
    public function __construct(
        public string $shiftId,
        public string $organizationId,
    ) {
    }
}
