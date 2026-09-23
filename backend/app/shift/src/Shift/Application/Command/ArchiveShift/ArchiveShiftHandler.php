<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\ArchiveShift;

use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Archiving keeps the shift and its targets, but moves it off the live list. There is no
 * counterpart command: archiving is one-way by design.
 */
#[AsMessageHandler]
final readonly class ArchiveShiftHandler
{
    public function __construct(
        private ShiftRepositoryInterface $shifts,
    ) {
    }

    public function __invoke(ArchiveShiftCommand $command): void
    {
        $shift = $this->shifts->findByIdForOrganization(
            ShiftId::fromString($command->shiftId),
            OrganizationId::fromString($command->organizationId),
        );

        if (null === $shift) {
            throw new ShiftNotFoundException();
        }

        $shift->archive(new \DateTimeImmutable());

        $this->shifts->save($shift);
    }
}
