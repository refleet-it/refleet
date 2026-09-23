<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\RecordShiftTargetResult;

use App\Shift\Shift\Domain\Shift\Enum\ShiftStatusEnum;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordShiftTargetResultHandler
{
    public function __construct(
        private ShiftTargetRepositoryInterface $shiftTargets,
        private ShiftRepositoryInterface $shifts,
    ) {
    }

    public function __invoke(RecordShiftTargetResultCommand $command): void
    {
        $target = $this->shiftTargets->findById(ShiftTargetId::fromString($command->targetId));

        if (null === $target) {
            return;
        }

        if ($command->success) {
            $target->recordChangeSuccess($command->summary, $command->branchName, $command->runnerName, $command->mergeRequestUrl, $command->mergeRequestIid);
        } else {
            $target->recordChangeFailure($command->errorMessage ?? $command->summary, $command->runnerName);
        }

        $this->shiftTargets->save($target);

        $this->completeIfFinished(ShiftId::fromString($command->shiftId));
    }

    private function completeIfFinished(ShiftId $shiftId): void
    {
        $remaining = $this->shiftTargets->countByShiftIdAndStatuses(
            $shiftId,
            ShiftTargetStatusEnum::inFlightStatuses(),
        );

        if ($remaining > 0) {
            return;
        }

        $shift = $this->shifts->findById($shiftId);

        // A trial run settling leaves a DRAFT shift a draft; a cancelled one stays cancelled.
        if (null === $shift || ShiftStatusEnum::APPLYING_CHANGE !== $shift->status()) {
            return;
        }

        $shift->complete();
        $this->shifts->save($shift);
    }
}
