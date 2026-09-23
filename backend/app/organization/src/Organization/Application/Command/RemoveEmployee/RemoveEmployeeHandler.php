<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\RemoveEmployee;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\CannotRemoveSelfException;
use App\Organization\Organization\Domain\Employee\Exception\EmployeeNotInOrganizationException;
use App\Organization\Organization\Domain\Employee\Exception\NotOrganizationOwnerException;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemoveEmployeeHandler
{
    public function __construct(
        private EmployeeRepositoryInterface $employeeRepository,
    ) {
    }

    public function __invoke(RemoveEmployeeCommand $command): void
    {
        if ($command->requestingAccountId === $command->targetAccountId) {
            throw new CannotRemoveSelfException();
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

        $target->leaveOrganization();
        $this->employeeRepository->save($target);
    }
}
