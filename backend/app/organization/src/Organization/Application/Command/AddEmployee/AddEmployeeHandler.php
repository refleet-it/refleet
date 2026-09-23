<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\AddEmployee;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\AccountAlreadyBelongsToOrganizationException;
use App\Organization\Organization\Domain\Employee\Exception\EmployeeNotFoundException;
use App\Organization\Organization\Domain\Employee\Exception\NotOrganizationOwnerException;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddEmployeeHandler
{
    public function __construct(
        private EmployeeRepositoryInterface $employeeRepository,
    ) {
    }

    public function __invoke(AddEmployeeCommand $command): AddedEmployee
    {
        $requester = $this->employeeRepository->findByAccountId(
            AccountId::fromString($command->requestingAccountId)
        );

        if (null === $requester) {
            throw new NotOrganizationOwnerException();
        }

        $organizationId = $requester->organizationId();

        if (null === $organizationId || RoleEnum::OWNER !== $requester->role()) {
            throw new NotOrganizationOwnerException();
        }

        $target = $this->employeeRepository->findByEmail($command->email);

        if (null === $target) {
            throw new EmployeeNotFoundException();
        }

        if ($target->isInOrganization()) {
            throw new AccountAlreadyBelongsToOrganizationException();
        }

        $target->joinOrganization($organizationId, RoleEnum::USER);
        $this->employeeRepository->save($target);

        return new AddedEmployee(
            accountId: $target->accountId()->asString(),
            email: $target->email(),
            role: RoleEnum::USER->value,
        );
    }
}
