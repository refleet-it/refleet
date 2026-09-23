<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\Shift\Repository;

use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;

interface ShiftRepositoryInterface
{
    public function save(Shift $shift): void;

    public function findById(ShiftId $id): ?Shift;

    /**
     * Like findById, but scoped at the query level so a shift belonging to another
     * organization comes back as null instead of having to be filtered out by the caller.
     */
    public function findByIdForOrganization(ShiftId $id, OrganizationId $organizationId): ?Shift;

    /**
     * @return Shift[] ordered by created_at DESC
     */
    public function findByOrganizationId(OrganizationId $organizationId): array;

    /**
     * Live and archived shifts never share a page: $archived picks which side to list.
     *
     * @return ListResponse<Shift>
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
