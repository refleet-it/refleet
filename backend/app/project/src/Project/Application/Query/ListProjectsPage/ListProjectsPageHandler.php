<?php

declare(strict_types=1);

namespace App\Project\Project\Application\Query\ListProjectsPage;

use App\Project\Project\Application\Query\ListProjects\ProjectOverview;
use App\Project\Project\Domain\Project\Model\Project;
use App\Project\Project\Domain\Project\Repository\ProjectRepositoryInterface;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Shared\Domain\ValueObject\ListParameters;
use App\Shared\Domain\ValueObject\ListResponse;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListProjectsPageHandler
{
    private const array ALLOWED_SORT_FIELDS = ['name', 'createdAt', 'lastSyncedAt'];

    public function __construct(
        private ProjectRepositoryInterface $projects,
    ) {
    }

    /**
     * @return ListResponse<ProjectOverview>
     */
    public function __invoke(ListProjectsPageQuery $query): ListResponse
    {
        $organizationId = OrganizationId::fromString($query->organizationId);

        $parameters = ListParameters::fromRequest(
            page: $query->page,
            limit: $query->limit,
            sortBy: $query->sortBy,
            sortDirection: $query->sortDirection,
            allowedSortFields: self::ALLOWED_SORT_FIELDS,
        );

        $result = $this->projects->getPaginatedList($organizationId, $parameters->getPagination(), $parameters->getSorting());

        /* @var ListResponse<ProjectOverview> */
        return ListResponse::create(
            items: \array_map($this->toOverview(...), $result->getItems()),
            totalItems: $result->getTotalItems(),
            pagination: $result->getPagination(),
        );
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
