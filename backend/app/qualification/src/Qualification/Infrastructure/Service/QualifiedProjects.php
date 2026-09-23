<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Service;

use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationArchivedException;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationNotFoundException;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Shared\Domain\Service\QualifiedProjectsInterface;
use App\Shared\Domain\ValueObject\ProjectCatalogEntry;

final readonly class QualifiedProjects implements QualifiedProjectsInterface
{
    public function __construct(
        private QualificationRepositoryInterface $qualifications,
        private QualificationTargetRepositoryInterface $qualificationTargets,
    ) {
    }

    #[\Override]
    public function forQualification(string $organizationId, string $qualificationId, ?array $projectIds = null): array
    {
        $qualification = $this->qualifications->findByIdForOrganization(
            QualificationId::fromString($qualificationId),
            OrganizationId::fromString($organizationId),
        );

        if (null === $qualification) {
            throw new QualificationNotFoundException();
        }

        if ($qualification->isArchived()) {
            throw new QualificationArchivedException();
        }

        $wantsSpecificProjects = null !== $projectIds && [] !== $projectIds;

        $targets = $wantsSpecificProjects
            ? $this->qualificationTargets->findByQualificationId($qualification->id())
            : $this->qualificationTargets->findByQualificationIdAndStatuses($qualification->id(), [QualificationTargetStatusEnum::QUALIFIED]);

        if ($wantsSpecificProjects) {
            $wanted = \array_flip($projectIds);
            $targets = \array_values(\array_filter(
                $targets,
                static fn (QualificationTarget $target): bool => isset($wanted[$target->projectId()->asString()]),
            ));
        }

        return \array_map($this->toEntry(...), $targets);
    }

    private function toEntry(QualificationTarget $target): ProjectCatalogEntry
    {
        $snapshot = $target->projectSnapshot();

        return new ProjectCatalogEntry(
            id: $target->projectId()->asString(),
            externalId: $snapshot->externalId(),
            name: $snapshot->name(),
            path: $snapshot->path(),
            defaultBranch: $snapshot->defaultBranch(),
        );
    }
}
