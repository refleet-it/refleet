<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\GetShift;

use App\Shared\Domain\ValueObject\PromptSource;
use App\Shift\Shift\Application\Query\ListShifts\ListShiftsHandler;
use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetShiftHandler
{
    public function __construct(
        private ShiftRepositoryInterface $shifts,
        private ShiftTargetRepositoryInterface $shiftTargets,
    ) {
    }

    public function __invoke(GetShiftQuery $query): ShiftDetail
    {
        $shift = $this->shifts->findByIdForOrganization(
            ShiftId::fromString($query->shiftId),
            OrganizationId::fromString($query->organizationId),
        );

        if (null === $shift) {
            throw new ShiftNotFoundException();
        }

        $breakdown = $this->shiftTargets->statusBreakdown($shift->id());
        $targetCount = \array_sum($breakdown);
        $criteria = $shift->changeCriteria();

        return new ShiftDetail(
            id: $shift->id()->asString(),
            organizationId: $shift->organizationId()->asString(),
            title: $shift->title(),
            description: $shift->description(),
            createdBy: $shift->createdBy()->asString(),
            status: $shift->status()->value,
            qualificationId: $shift->qualificationId()?->asString(),
            changeMode: $criteria?->mode()->value,
            changeEngine: $criteria?->engine()?->value,
            changePrompt: $criteria?->prompt(),
            changeModel: $criteria?->model(),
            changeRules: $criteria?->rules(),
            changeSources: \array_map(static fn (PromptSource $source): array => $source->toArray(), $criteria?->sources() ?? []),
            cancelReason: $shift->cancelReason(),
            targetCount: $targetCount,
            statusBreakdown: $breakdown,
            progressPercent: ListShiftsHandler::progressPercent($breakdown, $targetCount),
            createdAt: $shift->createdAt()->format('c'),
            changeStartedAt: $shift->changeStartedAt()?->format('c'),
            completedAt: $shift->completedAt()?->format('c'),
            cancelledAt: $shift->cancelledAt()?->format('c'),
            archivedAt: $shift->archivedAt()?->format('c'),
        );
    }
}
