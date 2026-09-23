<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\CreateOrganization;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\AccountAlreadyBelongsToOrganizationException;
use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\Model\Organization;
use App\Organization\Organization\Domain\Organization\Repository\OrganizationRepositoryInterface;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateOrganizationHandler
{
    public function __construct(
        private EmployeeRepositoryInterface $employeeRepository,
        private OrganizationRepositoryInterface $organizationRepository,
    ) {
    }

    public function __invoke(CreateOrganizationCommand $command): CreatedOrganization
    {
        $accountId = AccountId::fromString($command->accountId);
        $employee = $this->employeeRepository->findByAccountId($accountId);

        if (null === $employee) {
            // The account-created listener mirrors every account into an Employee row, but
            // accounts predating that listener (or seeded directly, e.g. fixtures) may not
            // have one yet. Creating an organization is a fine time to backfill it.
            $employee = Employee::mirror($accountId, $command->email);
        }

        if ($employee->isInOrganization()) {
            throw new AccountAlreadyBelongsToOrganizationException();
        }

        $organizationId = OrganizationId::generate();
        $organization = Organization::create($organizationId, $command->name, $accountId);
        $employee->joinOrganization($organizationId, RoleEnum::OWNER);

        $this->organizationRepository->save($organization);
        $this->employeeRepository->save($employee);

        return new CreatedOrganization(
            id: $organizationId->asString(),
            name: $organization->name(),
            role: RoleEnum::OWNER->value,
        );
    }
}
