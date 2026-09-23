<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Bus\AccountMirrored;

use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AccountMirroredMessageHandler
{
    public function __construct(
        private EmployeeRepositoryInterface $repository,
    ) {
    }

    public function __invoke(AccountMirroredMessage $message): void
    {
        $accountId = AccountId::fromString($message->accountId);

        if (null !== $this->repository->findByAccountId($accountId)) {
            return;
        }

        $this->repository->save(Employee::mirror($accountId, $message->email));
    }
}
