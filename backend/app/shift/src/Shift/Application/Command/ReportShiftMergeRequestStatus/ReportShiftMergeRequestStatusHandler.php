<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\ReportShiftMergeRequestStatus;

use App\Shift\Shift\Domain\Shift\Exception\InvalidShiftStateTransitionException;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Exception\ShiftTargetNotFoundException;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Skeleton for a future GitLab webhook integration — currently only reachable through
 * the architect-facing/documented REST endpoint or the GitLab webhook controller.
 */
#[AsMessageHandler]
final readonly class ReportShiftMergeRequestStatusHandler
{
    public function __construct(
        private ShiftTargetRepositoryInterface $shiftTargets,
        private ShiftRepositoryInterface $shifts,
    ) {
    }

    public function __invoke(ReportShiftMergeRequestStatusCommand $command): void
    {
        $target = $this->findOwnedTarget($command->shiftTargetId, $command->organizationId);

        match ($command->status) {
            'opened' => $target->recordMergeRequestOpened($command->url, $command->externalIid),
            'merged' => $target->recordMergeRequestMerged(),
            'closed' => $target->recordMergeRequestClosed(),
            default => throw new \InvalidArgumentException(\sprintf('Unknown merge request status "%s".', $command->status)),
        };

        $this->shiftTargets->save($target);

        if (\in_array($command->status, ['merged', 'closed'], true)) {
            $this->completeShiftIfFinished($target->shiftId());
        }
    }

    private function completeShiftIfFinished(ShiftId $shiftId): void
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

    private function findOwnedTarget(string $shiftTargetId, string $organizationId): ShiftTarget
    {
        $target = $this->shiftTargets->findById(ShiftTargetId::fromString($shiftTargetId));

        if (null === $target || !$target->organizationId()->equals(OrganizationId::fromString($organizationId))) {
            throw new ShiftTargetNotFoundException();
        }

        return $target;
    }
}
