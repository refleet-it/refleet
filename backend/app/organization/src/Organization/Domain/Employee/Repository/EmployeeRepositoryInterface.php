<?php

declare(strict_types=1);

namespace App\Organization\Organization\Domain\Employee\Repository;

use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;

interface EmployeeRepositoryInterface
{
    public function save(Employee $employee): void;

    public function findByAccountId(AccountId $accountId): ?Employee;

    public function findByEmail(string $email): ?Employee;

    /**
     * @return Employee[]
     */
    public function findByOrganizationId(OrganizationId $organizationId): array;

    /**
     * @return ListResponse<Employee>
     */
    public function getPaginatedList(OrganizationId $organizationId, PaginationParameters $pagination, ?SortParameters $sorting): ListResponse;
}
