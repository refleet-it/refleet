<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\ListShifts;

use App\Shared\Domain\ValueObject\ListParameters;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListShiftsHandler
{
    private const array ALLOWED_SORT_FIELDS = ['title', 'status', 'createdAt'];

    public function __construct(
        private ShiftRepositoryInterface $shifts,
        private ShiftTargetRepositoryInterface $shiftTargets,
    ) {
    }

    /**
     * @return ListResponse<ShiftOverview>
     */
    public function __invoke(ListShiftsQuery $query): ListResponse
    {
        $organizationId = OrganizationId::fromString($query->organizationId);

        $parameters = ListParameters::fromRequest(
            page: $query->page,
            limit: $query->limit,
            sortBy: $query->sortBy,
            sortDirection: $query->sortDirection,
            allowedSortFields: self::ALLOWED_SORT_FIELDS,
        );

        $result = $this->shifts->getPaginatedList($organizationId, $parameters->getPagination(), $parameters->getSorting(), $query->search, $query->status, $query->archived);

        /* @var ListResponse<ShiftOverview> */
        return ListResponse::create(
            items: \array_map($this->toOverview(...), $result->getItems()),
            totalItems: $result->getTotalItems(),
            pagination: $result->getPagination(),
        );
    }

    /**
     * @param array<string, int> $statusBreakdown
     */
    public static function progressPercent(array $statusBreakdown, int $targetCount): ?int
    {
        if (0 === $targetCount) {
            return null;
        }

        $inFlight = 0;

        foreach (ShiftTargetStatusEnum::inFlightStatuses() as $status) {
            $inFlight += $statusBreakdown[$status->value] ?? 0;
        }

        $settled = $targetCount - $inFlight;

        return (int) \round(($settled / $targetCount) * 100);
    }

    /**
     * @param array<string, int> $statusBreakdown
     */
    public static function terminalTargetCount(array $statusBreakdown): int
    {
        $count = 0;

        foreach ($statusBreakdown as $status => $statusCount) {
            if (ShiftTargetStatusEnum::from($status)->isTerminal()) {
                $count += $statusCount;
            }
        }

        return $count;
    }

    private function toOverview(Shift $shift): ShiftOverview
    {
        $breakdown = $this->shiftTargets->statusBreakdown($shift->id());
        $targetCount = \array_sum($breakdown);

        return new ShiftOverview(
            id: $shift->id()->asString(),
            title: $shift->title(),
            description: $shift->description(),
            status: $shift->status()->value,
            qualificationId: $shift->qualificationId()?->asString(),
            changeMode: $shift->changeCriteria()?->mode()->value,
            targetCount: $targetCount,
            terminalTargetCount: self::terminalTargetCount($breakdown),
            statusBreakdown: $breakdown,
            progressPercent: self::progressPercent($breakdown, $targetCount),
            createdAt: $shift->createdAt()->format('c'),
            archivedAt: $shift->archivedAt()?->format('c'),
        );
    }
}
