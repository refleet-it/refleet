<?php

declare(strict_types=1);

namespace App\Project\Project\Infrastructure\Service;

use App\Project\Project\Domain\Project\Model\Project;
use App\Project\Project\Domain\Project\Repository\ProjectRepositoryInterface;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Project\Project\Domain\Project\ValueObject\ProjectId;
use App\Shared\Domain\Service\ProjectCatalogInterface;
use App\Shared\Domain\ValueObject\ProjectCatalogEntry;

final readonly class ProjectCatalog implements ProjectCatalogInterface
{
    public function __construct(
        private ProjectRepositoryInterface $projects,
    ) {
    }

    #[\Override]
    public function allForOrganization(string $organizationId): array
    {
        return \array_map(
            $this->toEntry(...),
            $this->projects->findActiveByOrganizationId(OrganizationId::fromString($organizationId)),
        );
    }

    #[\Override]
    public function byIds(string $organizationId, array $projectIds): array
    {
        if ([] === $projectIds) {
            return [];
        }

        return \array_map(
            $this->toEntry(...),
            $this->projects->findByIds(
                OrganizationId::fromString($organizationId),
                \array_map(ProjectId::fromString(...), $projectIds),
            ),
        );
    }

    private function toEntry(Project $project): ProjectCatalogEntry
    {
        return new ProjectCatalogEntry(
            id: $project->id()->asString(),
            externalId: $project->externalId()->asString(),
            name: $project->name(),
            path: $project->path(),
            defaultBranch: $project->defaultBranch(),
        );
    }
}
