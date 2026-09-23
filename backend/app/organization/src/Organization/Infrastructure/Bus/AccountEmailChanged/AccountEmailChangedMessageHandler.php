<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Bus\AccountEmailChanged;

use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AccountEmailChangedMessageHandler
{
    public function __construct(
        private EmployeeRepositoryInterface $repository,
    ) {
    }

    public function __invoke(AccountEmailChangedMessage $message): void
    {
        $employee = $this->repository->findByAccountId(AccountId::fromString($message->accountId));

        if (null === $employee) {
            return;
        }

        if ($employee->syncEmail($message->email)) {
            $this->repository->save($employee);
        }
    }
}
