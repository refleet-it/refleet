<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RefreshToken\Domain\RefreshToken\Model;

use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\RefreshToken\Domain\RefreshToken\Model\RefreshToken;
use App\Identity\RefreshToken\Domain\RefreshToken\ValueObject\RefreshTokenId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefreshToken::class)]
final class RefreshTokenTest extends TestCase
{
    #[Test]
    public function creates_refresh_token_with_all_fields(): void
    {
        // Arrange
        $id = RefreshTokenId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $account = $this->createAccount();
        $token = 'sample_refresh_token_value';
        $expiresAt = new \DateTimeImmutable('+1 hour');

        // Act
        $refreshToken = RefreshToken::create($id, $account, $token, $expiresAt);

        // Assert
        Assert::assertSame($id->asString(), $refreshToken->id()->asString());
        Assert::assertSame($account, $refreshToken->account());
        Assert::assertSame($token, $refreshToken->token());
        Assert::assertSame($expiresAt->getTimestamp(), $refreshToken->expiresAt()->getTimestamp());
        Assert::assertFalse($refreshToken->isRevoked());
        Assert::assertTrue($refreshToken->isValid());
    }

    #[Test]
    public function revoke_marks_token_as_revoked_and_invalid(): void
    {
        // Arrange
        $refreshToken = RefreshToken::create(
            RefreshTokenId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            $this->createAccount(),
            'token_value',
            new \DateTimeImmutable('+1 hour')
        );

        // Act
        $refreshToken->revoke();

        // Assert
        Assert::assertTrue($refreshToken->isRevoked());
        Assert::assertFalse($refreshToken->isValid());
    }

    #[Test]
    public function set_revoked_toggles_revocation_state(): void
    {
        // Arrange
        $refreshToken = RefreshToken::create(
            RefreshTokenId::fromString('550e8400-e29b-41d4-a716-446655440002'),
            $this->createAccount(),
            'token_value',
            new \DateTimeImmutable('+1 hour')
        );

        // Act & Assert
        Assert::assertFalse($refreshToken->isRevoked());

        $refreshToken->setRevoked(true);
        Assert::assertTrue($refreshToken->isRevoked());
        Assert::assertFalse($refreshToken->isValid());

        $refreshToken->setRevoked(false);
        Assert::assertFalse($refreshToken->isRevoked());
        Assert::assertTrue($refreshToken->isValid());
    }

    #[Test]
    public function is_expired_detects_past_expiration(): void
    {
        // Arrange
        $refreshToken = RefreshToken::create(
            RefreshTokenId::fromString('550e8400-e29b-41d4-a716-446655440003'),
            $this->createAccount(),
            'token_value',
            new \DateTimeImmutable('-1 second')
        );

        // Act & Assert
        Assert::assertTrue($refreshToken->isExpired());
        Assert::assertFalse($refreshToken->isValid());
    }

    #[Test]
    public function is_expired_is_false_for_future_expiration(): void
    {
        // Arrange
        $refreshToken = RefreshToken::create(
            RefreshTokenId::fromString('550e8400-e29b-41d4-a716-446655440004'),
            $this->createAccount(),
            'token_value',
            new \DateTimeImmutable('+10 minutes')
        );

        // Act & Assert
        Assert::assertFalse($refreshToken->isExpired());
        Assert::assertTrue($refreshToken->isValid());
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
