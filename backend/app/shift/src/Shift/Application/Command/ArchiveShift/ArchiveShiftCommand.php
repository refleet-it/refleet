<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\ArchiveShift;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ArchiveShiftCommand implements CommandInterface
{
    public function __construct(
        public string $shiftId,
        public string $organizationId,
    ) {
    }
}
