<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\GetQualificationTarget;

use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Exception\QualificationTargetNotFoundException;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetQualificationTargetHandler
{
    public function __construct(
        private QualificationTargetRepositoryInterface $qualificationTargets,
    ) {
    }

    public function __invoke(GetQualificationTargetQuery $query): QualificationTargetDetail
    {
        $target = $this->qualificationTargets->findById(QualificationTargetId::fromString($query->targetId));

        if (
            null === $target
            || !$target->organizationId()->equals(OrganizationId::fromString($query->organizationId))
            || !$target->qualificationId()->equals(QualificationId::fromString($query->qualificationId))
        ) {
            throw new QualificationTargetNotFoundException();
        }

        $snapshot = $target->projectSnapshot();

        return new QualificationTargetDetail(
            id: $target->id()->asString(),
            qualificationId: $target->qualificationId()->asString(),
            organizationId: $target->organizationId()->asString(),
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
            runnerJobId: $target->runnerJobId(),
            runnerName: $target->runnerName(),
            startedAt: $target->startedAt()?->format('c'),
            completedAt: $target->completedAt()?->format('c'),
            createdAt: $target->createdAt()->format('c'),
        );
    }
}
