<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Query\GetMyOrganization;

use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\Exception\OrganizationNotFoundException;
use App\Organization\Organization\Domain\Organization\Repository\OrganizationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetMyOrganizationHandler
{
    public function __construct(
        private EmployeeRepositoryInterface $employeeRepository,
        private OrganizationRepositoryInterface $organizationRepository,
    ) {
    }

    public function __invoke(GetMyOrganizationQuery $query): ?OrganizationOverview
    {
        $employee = $this->employeeRepository->findByAccountId(AccountId::fromString($query->accountId));
        $organizationId = $employee?->organizationId();

        if (null === $employee || null === $organizationId) {
            return null;
        }

        $organization = $this->organizationRepository->findById($organizationId);

        if (null === $organization) {
            throw new OrganizationNotFoundException();
        }

        $role = $employee->role();
        \assert(null !== $role);

        return new OrganizationOverview(
            id: $organization->id()->asString(),
            name: $organization->name(),
            role: $role->value,
        );
    }
}
