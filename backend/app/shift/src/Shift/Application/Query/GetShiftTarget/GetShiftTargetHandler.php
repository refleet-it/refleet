<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\GetShiftTarget;

use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Exception\ShiftTargetNotFoundException;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetShiftTargetHandler
{
    public function __construct(
        private ShiftTargetRepositoryInterface $shiftTargets,
    ) {
    }

    public function __invoke(GetShiftTargetQuery $query): ShiftTargetDetail
    {
        $target = $this->shiftTargets->findById(ShiftTargetId::fromString($query->shiftTargetId));

        if (
            null === $target
            || !$target->organizationId()->equals(OrganizationId::fromString($query->organizationId))
            || !$target->shiftId()->equals(ShiftId::fromString($query->shiftId))
        ) {
            throw new ShiftTargetNotFoundException();
        }

        $snapshot = $target->projectSnapshot();

        return new ShiftTargetDetail(
            id: $target->id()->asString(),
            shiftId: $target->shiftId()->asString(),
            organizationId: $target->organizationId()->asString(),
            projectId: $target->projectId()->asString(),
            projectSnapshotExternalId: $snapshot->externalId(),
            projectSnapshotPath: $snapshot->path(),
            projectSnapshotName: $snapshot->name(),
            projectSnapshotDefaultBranch: $snapshot->defaultBranch(),
            status: $target->status()->value,
            changeSummary: $target->changeSummary(),
            changeBranchName: $target->changeBranchName(),
            runnerJobId: $target->runnerJobId(),
            runnerName: $target->runnerName(),
            changeStartedAt: $target->changeStartedAt()?->format('c'),
            changeCompletedAt: $target->changeCompletedAt()?->format('c'),
            mergeRequestUrl: $target->mergeRequestUrl(),
            mergeRequestExternalIid: $target->mergeRequestExternalIid(),
            mergeRequestStatus: $target->mergeRequestStatus()->value,
            mergeRequestUpdatedAt: ($target->mergeRequestClosedAt() ?? $target->mergeRequestMergedAt() ?? $target->mergeRequestOpenedAt())?->format('c'),
            createdAt: $target->createdAt()->format('c'),
        );
    }
}
