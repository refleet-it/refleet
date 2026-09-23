<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Service;

use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Shared\Domain\Service\UserEmailProviderInterface;
use App\Shared\Domain\User\UserId;

final readonly class UserEmailProvider implements UserEmailProviderInterface
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository,
    ) {
    }

    #[\Override]
    public function getEmailByUserId(UserId $userId): ?string
    {
        $account = $this->accountRepository->findById($userId);

        return $account?->email();
    }
}
