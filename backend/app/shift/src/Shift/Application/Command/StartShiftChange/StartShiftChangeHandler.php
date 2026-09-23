<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\StartShiftChange;

use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\ValueObject\Id;
use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\Service\ShiftJobPayloadFactory;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Infrastructure\Bus\RunnerJobRequested\RunnerJobRequestedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class StartShiftChangeHandler
{
    public function __construct(
        private ShiftRepositoryInterface $shifts,
        private ShiftTargetRepositoryInterface $shiftTargets,
        private MessageBusInterface $bus,
        private ShiftJobPayloadFactory $payloadFactory,
    ) {
    }

    public function __invoke(StartShiftChangeCommand $command): void
    {
        $shift = $this->findOwnedShift($command->shiftId, $command->organizationId);

        $shift->startChange();

        $targets = $this->shiftTargets->findByShiftIdAndStatuses(
            $shift->id(),
            [ShiftTargetStatusEnum::PENDING_CHANGE],
        );

        // Nothing left to start (every target was trial-run or cancelled) and nothing still
        // running from a trial: the batch is already settled.
        if ([] === $targets && 0 === $this->shiftTargets->countByShiftIdAndStatuses($shift->id(), ShiftTargetStatusEnum::inFlightStatuses())) {
            $shift->complete();
            $this->shifts->save($shift);

            return;
        }

        $this->shifts->save($shift);

        $criteria = $shift->changeCriteria();
        \assert(null !== $criteria);

        foreach ($targets as $target) {
            // The job id is minted here so the target can record it right away; the Runner
            // context picks it up from the message instead of replying with one.
            $jobId = Id::generate()->asString();

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

            $target->startChange($jobId);
        }

        $this->shiftTargets->saveAll($targets);
    }

    private function findOwnedShift(string $shiftId, string $organizationId): Shift
    {
        $shift = $this->shifts->findByIdForOrganization(ShiftId::fromString($shiftId), OrganizationId::fromString($organizationId));

        if (null === $shift) {
            throw new ShiftNotFoundException();
        }

        return $shift;
    }
}
