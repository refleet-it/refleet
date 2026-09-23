<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Command\CreateQualification;

use App\Qualification\Qualification\Domain\Qualification\Exception\NoTargetProjectsResolvedException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\ProjectId;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use App\Shared\Domain\Service\ProjectCatalogInterface;
use App\Shared\Domain\ValueObject\ProjectCatalogEntry;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shared\Domain\ValueObject\PromptSource;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateQualificationHandler
{
    public function __construct(
        private QualificationRepositoryInterface $qualifications,
        private QualificationTargetRepositoryInterface $qualificationTargets,
        private ProjectCatalogInterface $projectCatalog,
    ) {
    }

    public function __invoke(CreateQualificationCommand $command): CreatedQualification
    {
        $organizationId = OrganizationId::fromString($command->organizationId);
        $criteria = $this->resolveCriteria($command);
        $projects = $this->resolveTargetProjects($command);

        if ([] === $projects) {
            throw new NoTargetProjectsResolvedException();
        }

        $qualification = Qualification::draft(
            id: QualificationId::generate(),
            organizationId: $organizationId,
            title: $command->title,
            description: $command->description,
            createdBy: AccountId::fromString($command->createdByAccountId),
            criteria: $criteria,
        );

        $targets = \array_map(
            fn (ProjectCatalogEntry $project): QualificationTarget => $this->createTarget($project, $qualification->id(), $organizationId),
            $projects,
        );

        $this->qualifications->save($qualification);
        $this->qualificationTargets->saveAll($targets);

        return new CreatedQualification(
            id: $qualification->id()->asString(),
            title: $qualification->title(),
            description: $qualification->description(),
            status: $qualification->status()->value,
            qualificationMode: $criteria->mode()->value,
            targetCount: \count($targets),
            createdAt: $qualification->createdAt()->format('c'),
        );
    }

    private function createTarget(ProjectCatalogEntry $project, QualificationId $qualificationId, OrganizationId $organizationId): QualificationTarget
    {
        $snapshot = new ProjectSnapshot(
            externalId: $project->externalId,
            path: $project->path,
            name: $project->name,
            defaultBranch: $project->defaultBranch,
        );

        return QualificationTarget::create(
            QualificationTargetId::generate(),
            $qualificationId,
            $organizationId,
            ProjectId::fromString($project->id),
            $snapshot,
        );
    }

    private function resolveCriteria(CreateQualificationCommand $command): QualificationCriteria
    {
        $engine = null !== $command->qualificationEngine ? CriteriaEngineEnum::from($command->qualificationEngine) : null;

        return match (CriteriaModeEnum::from($command->qualificationMode)) {
            CriteriaModeEnum::AI => QualificationCriteria::ai(
                $command->qualificationPrompt ?? '',
                $command->qualificationModel,
                $engine,
                $command->qualificationRules,
                \array_map(PromptSource::fromArray(...), $command->qualificationSources),
            ),
        };
    }

    /**
     * @return ProjectCatalogEntry[]
     */
    private function resolveTargetProjects(CreateQualificationCommand $command): array
    {
        return (null === $command->projectIds || [] === $command->projectIds)
            ? $this->projectCatalog->allForOrganization($command->organizationId)
            : $this->projectCatalog->byIds($command->organizationId, $command->projectIds);
    }
}
