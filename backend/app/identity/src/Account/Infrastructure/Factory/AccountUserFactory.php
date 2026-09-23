<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Factory;

use App\Identity\Account\Domain\Account\Model\Account;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\User\UserId;

final readonly class AccountUserFactory
{
    public function createFromAccount(Account $account, ?string $apiKeyId = null): AccountUser
    {
        return new AccountUser(
            $account->email(),
            [$account->role()->toSymfonyRole()],
            $account->passwordHash(),
            UserId::fromString($account->id()->asString()),
            apiKeyId: $apiKeyId,
        );
    }
}
