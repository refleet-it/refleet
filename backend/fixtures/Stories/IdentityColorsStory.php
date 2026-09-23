<?php

declare(strict_types=1);

namespace App\Fixtures\Stories;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Fixtures\Factory\Identity\PasswordResetTokenFactory;
use App\Fixtures\Factory\Identity\RefreshTokenFactory;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use Zenstruck\Foundry\Story;

final class IdentityColorsStory extends Story
{
    public function build(): void
    {
        $user = AccountFactory::createOne([
            'email' => Email::fromString('user@refleet.test'),
            'role' => RoleEnum::USER,
            'hashedPassword' => HashedPassword::fromString(AccountFactory::TEST_HASHED_PASSWORD),
        ]);

        $admin = AccountFactory::createOne([
            'email' => Email::fromString('admin@refleet.test'),
            'role' => RoleEnum::ADMINISTRATOR,
            'hashedPassword' => HashedPassword::fromString(AccountFactory::TEST_HASHED_PASSWORD),
        ]);

        $additionalAccounts = [];
        for ($i = 1; $i <= 14; ++$i) {
            $account = AccountFactory::createOne([
                'email' => Email::fromString("user{$i}@test.pl"),
                'role' => RoleEnum::USER,
                'hashedPassword' => HashedPassword::fromString(AccountFactory::TEST_HASHED_PASSWORD),
            ]);
            $additionalAccounts[] = $account;
        }

        RefreshTokenFactory::createOne([
            'account' => $user,
            'token' => 'user_refresh_token_'.\bin2hex(\random_bytes(16)),
            'expiresAt' => new \DateTimeImmutable('+1 week'),
        ]);

        PasswordResetTokenFactory::createOne([
            'account' => $additionalAccounts[0],
            'token' => 'reset_token_user_'.\bin2hex(\random_bytes(16)),
            'expiresAt' => new \DateTimeImmutable('+1 hour'),
        ]);

        PasswordResetTokenFactory::createOne([
            'account' => $admin,
            'token' => 'expired_reset_token_'.\bin2hex(\random_bytes(16)),
            'expiresAt' => new \DateTimeImmutable('-1 hour'),
        ]);

        $revokedRefreshToken = RefreshTokenFactory::createOne([
            'account' => $user,
            'token' => 'revoked_refresh_token_'.\bin2hex(\random_bytes(16)),
            'expiresAt' => new \DateTimeImmutable('+1 week'),
        ]);
        $revokedRefreshToken->revoke();
    }
}
