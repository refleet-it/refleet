<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\CancelShift;

use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Infrastructure\Bus\OwnerJobsCancellationRequested\OwnerJobsCancellationRequestedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Cascades to non-terminal targets and to any PENDING/CLAIMED runner jobs still in
 * flight for this shift, so nothing keeps running against a cancelled batch.
 */
#[AsMessageHandler]
final readonly class CancelShiftHandler
{
    public function __construct(
        private ShiftRepositoryInterface $shifts,
        private ShiftTargetRepositoryInterface $shiftTargets,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(CancelShiftCommand $command): void
    {
        $shift = $this->findOwnedShift($command->shiftId, $command->organizationId);

        $shift->cancel($command->reason);

        $this->shifts->save($shift);

        $nonTerminalTargets = $this->shiftTargets->findNonTerminalByShiftId($shift->id());

        foreach ($nonTerminalTargets as $target) {
            $target->cancel();
        }

        $this->shiftTargets->saveAll($nonTerminalTargets);

        $this->bus->dispatch(new OwnerJobsCancellationRequestedMessage(
            ownerId: $shift->id()->asString(),
            organizationId: $shift->organizationId()->asString(),
        ));
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
