<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Model;

use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Model\PasswordResetToken;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordResetToken::class)]
final class PasswordResetTokenTest extends TestCase
{
    #[Test]
    public function creates_password_reset_token_with_all_fields(): void
    {
        // Arrange
        $id = Id::fromString('550e8400-e29b-41d4-a716-446655440000');
        $account = $this->createAccount();
        $token = 'sample_password_reset_token_value';
        $expiresAt = new \DateTimeImmutable('+1 hour');

        // Act
        $passwordResetToken = PasswordResetToken::create($id, $account, $token, $expiresAt);

        // Assert
        Assert::assertSame($id->asString(), $passwordResetToken->id()->asString());
        Assert::assertSame($account, $passwordResetToken->account());
        Assert::assertSame($token, $passwordResetToken->token());
        Assert::assertSame($expiresAt->getTimestamp(), $passwordResetToken->expiresAt()->getTimestamp());
        Assert::assertFalse($passwordResetToken->isUsed());
        Assert::assertFalse($passwordResetToken->isExpired());
    }

    #[Test]
    public function mark_as_used_sets_flag(): void
    {
        // Arrange
        $passwordResetToken = PasswordResetToken::create(
            Id::fromString('550e8400-e29b-41d4-a716-446655440001'),
            $this->createAccount(),
            'token_value',
            new \DateTimeImmutable('+1 hour')
        );

        // Act
        $passwordResetToken->markAsUsed();

        // Assert
        Assert::assertTrue($passwordResetToken->isUsed());
    }

    #[Test]
    public function is_expired_detects_past_expiration(): void
    {
        // Arrange
        $passwordResetToken = PasswordResetToken::create(
            Id::fromString('550e8400-e29b-41d4-a716-446655440002'),
            $this->createAccount(),
            'token_value',
            new \DateTimeImmutable('-1 second')
        );

        // Act & Assert
        Assert::assertTrue($passwordResetToken->isExpired());
    }

    #[Test]
    public function is_expired_is_false_for_future_expiration(): void
    {
        // Arrange
        $passwordResetToken = PasswordResetToken::create(
            Id::fromString('550e8400-e29b-41d4-a716-446655440003'),
            $this->createAccount(),
            'token_value',
            new \DateTimeImmutable('+10 minutes')
        );

        // Act & Assert
        Assert::assertFalse($passwordResetToken->isExpired());
    }

    private function createAccount(): Account
    {
        return Account::create(
            AccountId::fromString('11111111-1111-1111-1111-111111111111'),
            Email::fromString('test@example.com'),
            HashedPassword::fromString('hashed_password_123'),
            RoleEnum::USER
        );
    }
}
