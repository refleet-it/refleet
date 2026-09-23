<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\CreateShift;

use App\Shared\Domain\Service\ProjectCatalogInterface;
use App\Shared\Domain\Service\QualifiedProjectsInterface;
use App\Shared\Domain\ValueObject\ProjectCatalogEntry;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Domain\Shift\Exception\ExplicitProjectSelectionRequiredException;
use App\Shift\Shift\Domain\Shift\Exception\NoTargetProjectsResolvedException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\AccountId;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\QualificationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ProjectId;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateShiftHandler
{
    public function __construct(
        private ShiftRepositoryInterface $shifts,
        private ShiftTargetRepositoryInterface $shiftTargets,
        private ProjectCatalogInterface $projectCatalog,
        private QualifiedProjectsInterface $qualifiedProjects,
    ) {
    }

    public function __invoke(CreateShiftCommand $command): CreatedShift
    {
        $organizationId = OrganizationId::fromString($command->organizationId);
        $qualificationId = null !== $command->qualificationId ? QualificationId::fromString($command->qualificationId) : null;

        $resolvedTargets = null === $qualificationId
            ? $this->resolveManualTargets($command)
            : $this->resolveQualificationTargets($qualificationId, $command);

        if ([] === $resolvedTargets) {
            throw new NoTargetProjectsResolvedException();
        }

        $shift = Shift::draft(
            id: ShiftId::generate(),
            organizationId: $organizationId,
            title: $command->title,
            description: $command->description,
            createdBy: AccountId::fromString($command->createdByAccountId),
            qualificationId: $qualificationId,
        );

        $targets = \array_map(
            static fn (array $resolved): ShiftTarget => ShiftTarget::create(
                ShiftTargetId::generate(),
                $shift->id(),
                $organizationId,
                ProjectId::fromString($resolved['projectId']),
                new ProjectSnapshot($resolved['externalId'], $resolved['path'], $resolved['name'], $resolved['defaultBranch']),
            ),
            $resolvedTargets,
        );

        $this->shifts->save($shift);
        $this->shiftTargets->saveAll($targets);

        return new CreatedShift(
            id: $shift->id()->asString(),
            title: $shift->title(),
            description: $shift->description(),
            status: $shift->status()->value,
            qualificationId: $shift->qualificationId()?->asString(),
            targetCount: \count($targets),
            createdAt: $shift->createdAt()->format('c'),
        );
    }

    /**
     * Mode 3: no qualification — a fully manual selection of any projects in the
     * organization. Requires an explicit, non-empty projectIds list.
     *
     * @return array<int, array{projectId: string, externalId: string, path: string, name: string, defaultBranch: ?string}>
     */
    private function resolveManualTargets(CreateShiftCommand $command): array
    {
        if (null === $command->projectIds || [] === $command->projectIds) {
            throw new ExplicitProjectSelectionRequiredException();
        }

        return \array_map(
            $this->toTarget(...),
            $this->projectCatalog->byIds($command->organizationId, $command->projectIds),
        );
    }

    /**
     * Mode 1 (no projectIds): every currently QUALIFIED target of the qualification.
     * Mode 2 (projectIds given): those specific targets, regardless of status.
     *
     * @return array<int, array{projectId: string, externalId: string, path: string, name: string, defaultBranch: ?string}>
     */
    private function resolveQualificationTargets(QualificationId $qualificationId, CreateShiftCommand $command): array
    {
        return \array_map(
            $this->toTarget(...),
            $this->qualifiedProjects->forQualification(
                $command->organizationId,
                $qualificationId->asString(),
                $command->projectIds,
            ),
        );
    }

    /**
     * @return array{projectId: string, externalId: string, path: string, name: string, defaultBranch: ?string}
     */
    private function toTarget(ProjectCatalogEntry $project): array
    {
        return [
            'projectId' => $project->id,
            'externalId' => $project->externalId,
            'path' => $project->path,
            'name' => $project->name,
            'defaultBranch' => $project->defaultBranch,
        ];
    }
}
