<?php

declare(strict_types=1);

namespace App\Runner\Runner\Domain\Runner\Repository;

use App\Runner\Runner\Domain\Runner\Model\Runner;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;

interface RunnerRepositoryInterface
{
    public function save(Runner $runner): void;

    public function findById(RunnerId $id): ?Runner;

    /**
     * Like findById, but scoped at the query level so a runner belonging to another
     * organization comes back as null instead of having to be filtered out by the caller.
     */
    public function findByIdForOrganization(RunnerId $id, OrganizationId $organizationId): ?Runner;

    /**
     * @return Runner[] ordered by name ASC
     */
    public function findByOrganizationId(OrganizationId $organizationId): array;

    /**
     * @param bool $archived true lists only archived runners, false only live ones
     *
     * @return ListResponse<Runner>
     */
    public function getPaginatedList(OrganizationId $organizationId, PaginationParameters $pagination, ?SortParameters $sorting, bool $archived = false): ListResponse;

    /**
     * Archived runners are never returned: their name is free for a new runner to take.
     */
    public function findByOrganizationIdAndName(OrganizationId $organizationId, string $name): ?Runner;
}
