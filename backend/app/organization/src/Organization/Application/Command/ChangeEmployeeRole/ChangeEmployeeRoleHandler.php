<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\ChangeEmployeeRole;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\CannotChangeOwnRoleException;
use App\Organization\Organization\Domain\Employee\Exception\EmployeeNotInOrganizationException;
use App\Organization\Organization\Domain\Employee\Exception\NotOrganizationOwnerException;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Only one non-owner role exists today (RoleEnum::USER), so the only meaningful role
 * change is transferring ownership: the target becomes OWNER and the requesting owner
 * becomes a regular USER. There is no standalone "promote/demote to a middle role" case.
 */
#[AsMessageHandler]
final readonly class ChangeEmployeeRoleHandler
{
    public function __construct(
        private EmployeeRepositoryInterface $employeeRepository,
    ) {
    }

    public function __invoke(ChangeEmployeeRoleCommand $command): TransferredOwnership
    {
        if ($command->requestingAccountId === $command->targetAccountId) {
            throw new CannotChangeOwnRoleException();
        }

        $requester = $this->employeeRepository->findByAccountId(
            AccountId::fromString($command->requestingAccountId)
        );

        $organizationId = $requester?->organizationId();

        if (null === $organizationId || RoleEnum::OWNER !== $requester->role()) {
            throw new NotOrganizationOwnerException();
        }

        $target = $this->employeeRepository->findByAccountId(
            AccountId::fromString($command->targetAccountId)
        );

        if (null === $target || !$target->belongsTo($organizationId)) {
            throw new EmployeeNotInOrganizationException();
        }

        $target->changeRole(RoleEnum::OWNER);
        $requester->changeRole(RoleEnum::USER);

        $this->employeeRepository->save($target);
        $this->employeeRepository->save($requester);

        return new TransferredOwnership(
            newOwnerAccountId: $target->accountId()->asString(),
            newOwnerEmail: $target->email(),
        );
    }
}
