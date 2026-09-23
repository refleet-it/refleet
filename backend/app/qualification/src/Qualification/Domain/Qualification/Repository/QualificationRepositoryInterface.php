<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Domain\Qualification\Repository;

use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;

interface QualificationRepositoryInterface
{
    public function save(Qualification $qualification): void;

    public function findById(QualificationId $id): ?Qualification;

    /**
     * Like findById, but scoped at the query level so a qualification belonging to
     * another organization comes back as null instead of having to be filtered out by
     * the caller.
     */
    public function findByIdForOrganization(QualificationId $id, OrganizationId $organizationId): ?Qualification;

    /**
     * @return Qualification[] ordered by created_at DESC
     */
    public function findByOrganizationId(OrganizationId $organizationId): array;

    /**
     * Live and archived qualifications never share a page: $archived picks which side to list.
     *
     * @return ListResponse<Qualification>
     */
    public function getPaginatedList(
        OrganizationId $organizationId,
        PaginationParameters $pagination,
        ?SortParameters $sorting,
        ?string $search = null,
        ?string $status = null,
        bool $archived = false,
    ): ListResponse;
}
