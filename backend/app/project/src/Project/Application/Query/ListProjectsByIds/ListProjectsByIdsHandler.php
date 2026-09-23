<?php

declare(strict_types=1);

namespace App\Project\Project\Application\Query\ListProjectsByIds;

use App\Project\Project\Application\Query\ListProjects\ProjectOverview;
use App\Project\Project\Domain\Project\Model\Project;
use App\Project\Project\Domain\Project\Repository\ProjectRepositoryInterface;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Project\Project\Domain\Project\ValueObject\ProjectId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListProjectsByIdsHandler
{
    public function __construct(
        private ProjectRepositoryInterface $projects,
    ) {
    }

    /**
     * @return ProjectOverview[]
     */
    public function __invoke(ListProjectsByIdsQuery $query): array
    {
        if ([] === $query->ids) {
            return [];
        }

        $organizationId = OrganizationId::fromString($query->organizationId);
        $ids = \array_map(ProjectId::fromString(...), $query->ids);

        return \array_map($this->toOverview(...), $this->projects->findByIds($organizationId, $ids));
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
