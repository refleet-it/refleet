<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Service;

use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Shared\Application\Query\AccountEmailProviderInterface;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\Id;

final readonly class AccountEmailProvider implements AccountEmailProviderInterface
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository,
    ) {
    }

    #[\Override]
    public function getEmailByUserId(UserId $userId): ?string
    {
        $account = $this->accountRepository->findById(Id::fromString($userId->asString()));

        return $account?->email();
    }
}
