<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\ReportShiftMergeRequestStatus;

use App\Shift\Shift\Domain\Shift\Service\ShiftCompletion;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\ShiftTarget\Exception\ShiftTargetNotFoundException;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The architect-facing way to settle a merge request by hand. PollOpenMergeRequestsHandler
 * settles the same targets from what GitLab actually reports, so this covers what polling
 * cannot see: a merge request that moved, or one an architect wants written off without
 * touching GitLab.
 */
#[AsMessageHandler]
final readonly class ReportShiftMergeRequestStatusHandler
{
    public function __construct(
        private ShiftTargetRepositoryInterface $shiftTargets,
        private ShiftCompletion $shiftCompletion,
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
            $this->shiftCompletion->completeIfFinished($target->shiftId());
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
