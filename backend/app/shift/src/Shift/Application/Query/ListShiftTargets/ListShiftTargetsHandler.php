<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\ListShiftTargets;

use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListShiftTargetsHandler
{
    public function __construct(
        private ShiftRepositoryInterface $shifts,
        private ShiftTargetRepositoryInterface $shiftTargets,
    ) {
    }

    /**
     * @return ListResponse<ShiftTargetOverview>
     */
    public function __invoke(ListShiftTargetsQuery $query): ListResponse
    {
        $shift = $this->findOwnedShift($query->shiftId, $query->organizationId);
        $pagination = PaginationParameters::fromRequest($query->page, $query->limit);
        $status = null !== $query->status ? ShiftTargetStatusEnum::from($query->status) : null;

        $result = $this->shiftTargets->getPaginatedListByShiftId($shift->id(), $pagination, $status);

        /* @var ListResponse<ShiftTargetOverview> */
        return ListResponse::create(
            items: \array_map($this->toOverview(...), $result->getItems()),
            totalItems: $result->getTotalItems(),
            pagination: $result->getPagination(),
        );
    }

    private function toOverview(ShiftTarget $target): ShiftTargetOverview
    {
        $snapshot = $target->projectSnapshot();

        return new ShiftTargetOverview(
            id: $target->id()->asString(),
            shiftId: $target->shiftId()->asString(),
            projectId: $target->projectId()->asString(),
            projectName: $snapshot->name(),
            projectPath: $snapshot->path(),
            status: $target->status()->value,
            changeSummary: $target->changeSummary(),
            runnerName: $target->runnerName(),
            mergeRequestUrl: $target->mergeRequestUrl(),
            mergeRequestStatus: $target->mergeRequestStatus()->value,
            createdAt: $target->createdAt()->format('c'),
        );
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
