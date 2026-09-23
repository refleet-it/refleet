<?php

declare(strict_types=1);

namespace App\Project\Project\Application\Query\ListProjects;

use App\Project\Project\Domain\Project\Model\Project;
use App\Project\Project\Domain\Project\Repository\ProjectRepositoryInterface;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListProjectsHandler
{
    public function __construct(
        private ProjectRepositoryInterface $projects,
    ) {
    }

    /**
     * @return ProjectOverview[]
     */
    public function __invoke(ListProjectsQuery $query): array
    {
        $organizationId = OrganizationId::fromString($query->organizationId);

        return \array_map($this->toOverview(...), $this->projects->findActiveByOrganizationId($organizationId));
    }

    private function toOverview(Project $project): ProjectOverview
    {
        return new ProjectOverview(
            id: $project->id()->asString(),
            name: $project->name(),
            externalId: $project->externalId()->asString(),
            path: $project->path(),
            webUrl: $project->webUrl(),
            defaultBranch: $project->defaultBranch(),
            description: $project->description(),
            createdAt: $project->createdAt()->format('c'),
            lastSyncedAt: $project->lastSyncedAt()?->format('c'),
        );
    }
}
