<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\Shift\Service;

use App\Shift\Shift\Domain\Shift\Exception\InvalidShiftStateTransitionException;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;

/**
 * A shift is finished when none of its targets is still in flight. Targets are their own
 * aggregate and get settled one at a time — by the architect reporting a merge request,
 * or by the poller finding it merged — so every one of those paths has to ask the same
 * question afterwards, and asks it here.
 */
final readonly class ShiftCompletion
{
    public function __construct(
        private ShiftRepositoryInterface $shifts,
        private ShiftTargetRepositoryInterface $shiftTargets,
    ) {
    }

    public function completeIfFinished(ShiftId $shiftId): void
    {
        $remaining = $this->shiftTargets->countByShiftIdAndStatuses(
            $shiftId,
            ShiftTargetStatusEnum::inFlightStatuses(),
        );

        if ($remaining > 0) {
            return;
        }

        $shift = $this->shifts->findById($shiftId);

        if (null === $shift) {
            return;
        }

        try {
            $shift->complete();
            $this->shifts->save($shift);
        } catch (InvalidShiftStateTransitionException) {
            // Possible race with a parallel cancel() — safe to ignore, shift already left this phase.
        }
    }
}
