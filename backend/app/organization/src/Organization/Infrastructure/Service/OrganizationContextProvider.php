<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Service;

use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\Exception\OrganizationNotFoundException;
use App\Organization\Organization\Domain\Organization\Repository\OrganizationRepositoryInterface;
use App\Shared\Domain\Exception\AccountHasNoOrganizationException;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\OrganizationContext;

final readonly class OrganizationContextProvider implements OrganizationContextProviderInterface
{
    public function __construct(
        private EmployeeRepositoryInterface $employees,
        private OrganizationRepositoryInterface $organizations,
    ) {
    }

    #[\Override]
    public function requireForAccount(UserId $accountId): OrganizationContext
    {
        $employee = $this->employees->findByAccountId(AccountId::fromString($accountId->asString()));
        $organizationId = $employee?->organizationId();

        if (null === $employee || null === $organizationId) {
            throw new AccountHasNoOrganizationException();
        }

        $organization = $this->organizations->findById($organizationId);

        if (null === $organization) {
            throw new OrganizationNotFoundException();
        }

        $role = $employee->role();
        \assert(null !== $role);

        return new OrganizationContext(
            id: $organization->id()->asString(),
            name: $organization->name(),
            role: $role->value,
        );
    }
}
