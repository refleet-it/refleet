<?php

declare(strict_types=1);

namespace App\Project\Project\Domain\Project\Repository;

use App\Project\Project\Domain\Project\Model\Project;
use App\Project\Project\Domain\Project\ValueObject\GitLabProjectId;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Project\Project\Domain\Project\ValueObject\ProjectId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;

interface ProjectRepositoryInterface
{
    public function save(Project $project): void;

    public function findById(ProjectId $id): ?Project;

    public function findByExternalId(OrganizationId $organizationId, GitLabProjectId $externalId): ?Project;

    /**
     * @return Project[]
     */
    public function findActiveByOrganizationId(OrganizationId $organizationId): array;

    /**
     * @return ListResponse<Project>
     */
    public function getPaginatedList(OrganizationId $organizationId, PaginationParameters $pagination, ?SortParameters $sorting): ListResponse;

    /**
     * @param ProjectId[] $ids
     *
     * @return Project[]
     */
    public function findByIds(OrganizationId $organizationId, array $ids): array;
}
