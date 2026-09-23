<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\StartShiftTargetChange;

use App\Shared\Application\Command\Sync\CommandInterface;

/**
 * Runs the change on one target only: as a trial while the shift is still a draft (to see
 * what the prompt does before committing every project to it), or as a re-run of a target
 * that already settled.
 */
final readonly class StartShiftTargetChangeCommand implements CommandInterface
{
    public function __construct(
        public string $shiftId,
        public string $shiftTargetId,
        public string $organizationId,
    ) {
    }
}
