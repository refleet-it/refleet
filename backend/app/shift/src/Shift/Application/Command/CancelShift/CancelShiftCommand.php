<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\CancelShift;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class CancelShiftCommand implements CommandInterface
{
    public function __construct(
        public string $shiftId,
        public string $organizationId,
        public ?string $reason = null,
    ) {
    }
}
