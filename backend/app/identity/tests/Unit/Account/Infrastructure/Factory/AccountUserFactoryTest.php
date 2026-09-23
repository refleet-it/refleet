<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Factory;

use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Infrastructure\Factory\AccountUserFactory;
use App\Shared\Domain\User\UserId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountUserFactory::class)]
final class AccountUserFactoryTest extends TestCase
{
    #[Test]
    public function create_from_account_produces_expected_account_user(): void
    {
        // Arrange
        $account = Account::create(
            id: AccountId::generate(),
            email: Email::fromString('USER@Example.COM'),
            hashedPassword: HashedPassword::fromString('hashed-password-123'),
            role: RoleEnum::USER,
        );

        $factory = new AccountUserFactory();

        // Act
        $user = $factory->createFromAccount($account);

        // Assert
        Assert::assertSame($account->email(), $user->getUserIdentifier());
        Assert::assertSame([$account->role()->toSymfonyRole()], $user->getRoles());
        Assert::assertSame($account->passwordHash(), $user->getPassword());
        Assert::assertTrue($user->getUserId()->equals(UserId::fromString($account->id()->asString())));
    }
}
