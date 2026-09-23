<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\ListQualificationTargets;

use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationNotFoundException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListQualificationTargetsHandler
{
    public function __construct(
        private QualificationRepositoryInterface $qualifications,
        private QualificationTargetRepositoryInterface $qualificationTargets,
    ) {
    }

    /**
     * @return QualificationTargetOverview[]
     */
    public function __invoke(ListQualificationTargetsQuery $query): array
    {
        $qualification = $this->findOwnedQualification($query->qualificationId, $query->organizationId);

        $targets = null !== $query->status
            ? $this->qualificationTargets->findByQualificationIdAndStatuses($qualification->id(), [QualificationTargetStatusEnum::from($query->status)])
            : $this->qualificationTargets->findByQualificationId($qualification->id());

        if (null !== $query->projectIds && [] !== $query->projectIds) {
            $projectIds = \array_flip($query->projectIds);
            $targets = \array_values(\array_filter(
                $targets,
                static fn (QualificationTarget $target): bool => isset($projectIds[$target->projectId()->asString()]),
            ));
        }

        return \array_map($this->toOverview(...), $targets);
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
