<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\ListQualificationTargetsPage;

use App\Qualification\Qualification\Application\Query\ListQualificationTargets\QualificationTargetOverview;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationNotFoundException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListQualificationTargetsPageHandler
{
    public function __construct(
        private QualificationRepositoryInterface $qualifications,
        private QualificationTargetRepositoryInterface $qualificationTargets,
    ) {
    }

    /**
     * @return ListResponse<QualificationTargetOverview>
     */
    public function __invoke(ListQualificationTargetsPageQuery $query): ListResponse
    {
        $qualification = $this->findOwnedQualification($query->qualificationId, $query->organizationId);
        $pagination = PaginationParameters::fromRequest($query->page, $query->limit);
        $status = null !== $query->status ? QualificationTargetStatusEnum::from($query->status) : null;

        $result = $this->qualificationTargets->getPaginatedListByQualificationId($qualification->id(), $pagination, $status);

        /* @var ListResponse<QualificationTargetOverview> */
        return ListResponse::create(
            items: \array_map($this->toOverview(...), $result->getItems()),
            totalItems: $result->getTotalItems(),
            pagination: $result->getPagination(),
        );
    }

    private function toOverview(QualificationTarget $target): QualificationTargetOverview
    {
        $snapshot = $target->projectSnapshot();

        return new QualificationTargetOverview(
            id: $target->id()->asString(),
            qualificationId: $target->qualificationId()->asString(),
            projectId: $target->projectId()->asString(),
            projectSnapshotExternalId: $snapshot->externalId(),
            projectSnapshotPath: $snapshot->path(),
            projectSnapshotName: $snapshot->name(),
            projectSnapshotDefaultBranch: $snapshot->defaultBranch(),
            status: $target->status()->value,
            summary: $target->summary(),
            score: $target->score(),
            overridden: $target->overridden(),
            overrideNote: $target->overrideNote(),
            runnerName: $target->runnerName(),
            createdAt: $target->createdAt()->format('c'),
        );
    }

    private function findOwnedQualification(string $qualificationId, string $organizationId): Qualification
    {
        $qualification = $this->qualifications->findByIdForOrganization(QualificationId::fromString($qualificationId), OrganizationId::fromString($organizationId));

        if (null === $qualification) {
            throw new QualificationNotFoundException();
        }

        return $qualification;
    }
}
