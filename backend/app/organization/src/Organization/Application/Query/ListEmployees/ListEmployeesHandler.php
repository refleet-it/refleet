<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Query\ListEmployees;

use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\ValueObject\ListParameters;
use App\Shared\Domain\ValueObject\ListResponse;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListEmployeesHandler
{
    private const array ALLOWED_SORT_FIELDS = ['email', 'role', 'joinedOrganizationAt'];

    public function __construct(
        private EmployeeRepositoryInterface $employees,
    ) {
    }

    /**
     * @return ListResponse<EmployeeOverview>
     */
    public function __invoke(ListEmployeesQuery $query): ListResponse
    {
        $organizationId = OrganizationId::fromString($query->organizationId);

        $parameters = ListParameters::fromRequest(
            page: $query->page,
            limit: $query->limit,
            sortBy: $query->sortBy,
            sortDirection: $query->sortDirection,
            allowedSortFields: self::ALLOWED_SORT_FIELDS,
        );

        $result = $this->employees->getPaginatedList($organizationId, $parameters->getPagination(), $parameters->getSorting());

        /* @var ListResponse<EmployeeOverview> */
        return ListResponse::create(
            items: \array_map($this->toOverview(...), $result->getItems()),
            totalItems: $result->getTotalItems(),
            pagination: $result->getPagination(),
        );
    }

    private function toOverview(Employee $employee): EmployeeOverview
    {
        $role = $employee->role();
        \assert(null !== $role);

        return new EmployeeOverview(
            accountId: $employee->accountId()->asString(),
            email: $employee->email(),
            role: $role->value,
            joinedAt: $employee->joinedOrganizationAt()?->format('c'),
        );
    }
}
