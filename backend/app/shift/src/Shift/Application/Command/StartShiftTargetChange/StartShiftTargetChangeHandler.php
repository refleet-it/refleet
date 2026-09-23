<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\StartShiftTargetChange;

use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\ValueObject\Id;
use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\Service\ShiftJobPayloadFactory;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Exception\ShiftTargetNotFoundException;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use App\Shift\Shift\Infrastructure\Bus\RunnerJobRequested\RunnerJobRequestedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class StartShiftTargetChangeHandler
{
    public function __construct(
        private ShiftRepositoryInterface $shifts,
        private ShiftTargetRepositoryInterface $shiftTargets,
        private MessageBusInterface $bus,
        private ShiftJobPayloadFactory $payloadFactory,
    ) {
    }

    public function __invoke(StartShiftTargetChangeCommand $command): void
    {
        $shift = $this->findOwnedShift($command->shiftId, $command->organizationId);
        $target = $this->findOwnedTarget($command->shiftTargetId, $shift);

        $shift->startTargetChange();

        $criteria = $shift->changeCriteria();
        \assert(null !== $criteria);

        $jobId = Id::generate()->asString();

        if (ShiftTargetStatusEnum::PENDING_CHANGE === $target->status()) {
            $target->startChange($jobId);
        } else {
            $target->rerunChange($jobId);
        }

        $this->bus->dispatch(new RunnerJobRequestedMessage(
            jobId: $jobId,
            ownerId: $shift->id()->asString(),
            ownerTargetId: $target->id()->asString(),
            ownerLabel: $shift->title(),
            organizationId: $shift->organizationId()->asString(),
            kind: 'change',
            mode: $criteria->mode()->value,
            payload: $this->payloadFactory->build($criteria, $target->projectSnapshot()),
            engine: ($criteria->engine() ?? CriteriaEngineEnum::CLAUDE)->value,
        ));

        $this->shifts->save($shift);
        $this->shiftTargets->save($target);
    }

    private function findOwnedShift(string $shiftId, string $organizationId): Shift
    {
        $shift = $this->shifts->findByIdForOrganization(ShiftId::fromString($shiftId), OrganizationId::fromString($organizationId));

        if (null === $shift) {
            throw new ShiftNotFoundException();
        }

        return $shift;
    }

    private function findOwnedTarget(string $shiftTargetId, Shift $shift): ShiftTarget
    {
        $target = $this->shiftTargets->findById(ShiftTargetId::fromString($shiftTargetId));

        if (null === $target || !$target->shiftId()->equals($shift->id())) {
            throw new ShiftTargetNotFoundException();
        }

        return $target;
    }
}
