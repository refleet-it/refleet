<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Model;

use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Account::class)]
final class AccountTest extends TestCase
{
    #[Test]
    public function creates_account_with_all_fields(): void
    {
        $id = AccountId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'test@example.com';
        $passwordHash = 'hashed_password_123';
        $role = RoleEnum::USER;

        $account = Account::create($id, Email::fromString($email), HashedPassword::fromString($passwordHash), $role);

        Assert::assertSame($id->asString(), $account->id()->asString());
        Assert::assertSame(\mb_strtolower($email), $account->email());
        Assert::assertSame($passwordHash, $account->passwordHash());
        Assert::assertSame($role, $account->role());
        Assert::assertFalse($account->isActive());
        Assert::assertTrue($account->status()->isPendingEmailVerification());
    }

    #[Test]
    public function normalizes_email_to_lowercase(): void
    {
        $id = AccountId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'TEST@EXAMPLE.COM';
        $passwordHash = 'hashed_password_123';
        $role = RoleEnum::USER;

        $account = Account::create($id, Email::fromString($email), HashedPassword::fromString($passwordHash), $role);

        Assert::assertSame(\mb_strtolower($email), $account->email());
    }

    #[Test]
    public function deactivates_active_account(): void
    {
        $account = $this->createAccount();

        $account->deactivate();

        Assert::assertFalse($account->isActive());
    }

    #[Test]
    public function generates_refresh_token_and_clears_old_ones(): void
    {
        $account = $this->createAccount();
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $token = $account->generateRefreshToken('hashed-token', $expiresAt);

        Assert::assertSame($expiresAt->getTimestamp(), $token->expiresAt()->getTimestamp());
    }

    #[Test]
    public function generates_unique_refresh_token_each_time(): void
    {
        $account = $this->createAccount();
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $token1 = $account->generateRefreshToken('hashed-token-1', $expiresAt);
        $token2 = $account->generateRefreshToken('hashed-token-2', $expiresAt);

        Assert::assertNotSame($token1, $token2);
        Assert::assertNotSame($token1->token(), $token2->token());
    }

    #[Test]
    public function refresh_token_has_correct_expiration(): void
    {
        $account = $this->createAccount();
        $expiresAt = new \DateTimeImmutable('+2 hours');

        $token = $account->generateRefreshToken('hashed-token', $expiresAt);

        Assert::assertSame($expiresAt->getTimestamp(), $token->expiresAt()->getTimestamp());
    }

    private function createAccount(): Account
    {
        return Account::create(
            AccountId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            Email::fromString('test@example.com'),
            HashedPassword::fromString('hashed_password_123'),
            RoleEnum::USER
        );
    }
}
