<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\ListQualifications;

use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Shared\Domain\ValueObject\ListParameters;
use App\Shared\Domain\ValueObject\ListResponse;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListQualificationsHandler
{
    private const array ALLOWED_SORT_FIELDS = ['title', 'status', 'createdAt'];

    public function __construct(
        private QualificationRepositoryInterface $qualifications,
        private QualificationTargetRepositoryInterface $qualificationTargets,
    ) {
    }

    /**
     * @return ListResponse<QualificationOverview>
     */
    public function __invoke(ListQualificationsQuery $query): ListResponse
    {
        $organizationId = OrganizationId::fromString($query->organizationId);

        $parameters = ListParameters::fromRequest(
            page: $query->page,
            limit: $query->limit,
            sortBy: $query->sortBy,
            sortDirection: $query->sortDirection,
            allowedSortFields: self::ALLOWED_SORT_FIELDS,
        );

        $result = $this->qualifications->getPaginatedList($organizationId, $parameters->getPagination(), $parameters->getSorting(), $query->search, $query->status, $query->archived);

        /* @var ListResponse<QualificationOverview> */
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

        foreach (QualificationTargetStatusEnum::nonTerminalStatuses() as $status) {
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
            if (QualificationTargetStatusEnum::from($status)->isTerminal()) {
                $count += $statusCount;
            }
        }

        return $count;
    }

    private function toOverview(Qualification $qualification): QualificationOverview
    {
        $breakdown = $this->qualificationTargets->statusBreakdown($qualification->id());
        $targetCount = \array_sum($breakdown);

        return new QualificationOverview(
            id: $qualification->id()->asString(),
            title: $qualification->title(),
            description: $qualification->description(),
            status: $qualification->status()->value,
            qualificationMode: $qualification->criteria()->mode()->value,
            targetCount: $targetCount,
            terminalTargetCount: self::terminalTargetCount($breakdown),
            statusBreakdown: $breakdown,
            progressPercent: self::progressPercent($breakdown, $targetCount),
            createdAt: $qualification->createdAt()->format('c'),
            archivedAt: $qualification->archivedAt()?->format('c'),
        );
    }
}
