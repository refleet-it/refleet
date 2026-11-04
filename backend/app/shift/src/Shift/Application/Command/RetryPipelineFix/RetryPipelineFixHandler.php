<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\RetryPipelineFix;

use App\Shift\Shift\Domain\Shift\Exception\ShiftArchivedException;
use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Exception\ShiftTargetNotFoundException;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Only puts the target back in the poll's queue. Whether a job is worth queuing is still
 * the poll's decision, against what GitLab says now: the pipeline may have gone green, or
 * someone may have pushed to the branch, since the fix gave up.
 */
#[AsMessageHandler]
final readonly class RetryPipelineFixHandler
{
    public function __construct(
        private ShiftRepositoryInterface $shifts,
        private ShiftTargetRepositoryInterface $shiftTargets,
    ) {
    }

    public function __invoke(RetryPipelineFixCommand $command): void
    {
        $shift = $this->shifts->findByIdForOrganization(ShiftId::fromString($command->shiftId), OrganizationId::fromString($command->organizationId));

        if (null === $shift) {
            throw new ShiftNotFoundException();
        }

        if ($shift->isArchived()) {
            throw new ShiftArchivedException();
        }

        $target = $this->shiftTargets->findById(ShiftTargetId::fromString($command->shiftTargetId));

        if (null === $target || !$target->shiftId()->equals($shift->id())) {
            throw new ShiftTargetNotFoundException();
        }

        $target->retryPipelineFix();

        $this->shiftTargets->save($target);
    }
}
