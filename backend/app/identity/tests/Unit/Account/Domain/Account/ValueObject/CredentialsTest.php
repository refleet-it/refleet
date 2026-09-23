<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\ValueObject;

use App\Identity\Account\Domain\Account\ValueObject\Credentials;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CredentialsTest extends TestCase
{
    #[Test]
    public function creates_credentials_with_email_and_password_hash(): void
    {
        // Arrange
        $email = 'test@example.com';
        $passwordHash = 'hashed_password_123';

        // Act
        $credentials = new Credentials($email, $passwordHash);

        // Assert
        Assert::assertSame($email, $credentials->email());
        Assert::assertSame($passwordHash, $credentials->passwordHash());
    }

    #[Test]
    public function equals_returns_true_when_credentials_are_identical(): void
    {
        // Arrange
        $email = 'test@example.com';
        $passwordHash = 'hashed_password_123';
        $credentials1 = new Credentials($email, $passwordHash);
        $credentials2 = new Credentials($email, $passwordHash);

        // Act
        $result = $credentials1->equals($credentials2);

        // Assert
        Assert::assertTrue($result);
    }

    #[Test]
    public function equals_returns_false_when_emails_are_different(): void
    {
        // Arrange
        $passwordHash = 'hashed_password_123';
        $credentials1 = new Credentials('test1@example.com', $passwordHash);
        $credentials2 = new Credentials('test2@example.com', $passwordHash);

        // Act
        $result = $credentials1->equals($credentials2);

        // Assert
        Assert::assertFalse($result);
    }

    #[Test]
    public function equals_returns_false_when_password_hashes_are_different(): void
    {
        // Arrange
        $email = 'test@example.com';
        $credentials1 = new Credentials($email, 'hash1');
        $credentials2 = new Credentials($email, 'hash2');

        // Act
        $result = $credentials1->equals($credentials2);

        // Assert
        Assert::assertFalse($result);
    }

    #[Test]
    public function verify_password_returns_true_when_password_matches(): void
    {
        // Arrange
        $email = 'test@example.com';
        $passwordHash = 'hashed_password_123';
        $plainPassword = 'correct_password';
        $credentials = new Credentials($email, $passwordHash);

        $verifyCallback = static fn (string $plain, string $hash): bool => 'correct_password' === $plain && $hash === $passwordHash;

        // Act
        $result = $credentials->verifyPassword($plainPassword, $verifyCallback);

        // Assert
        Assert::assertTrue($result);
    }

    #[Test]
    public function verify_password_returns_false_when_password_does_not_match(): void
    {
        // Arrange
        $email = 'test@example.com';
        $passwordHash = 'hashed_password_123';
        $plainPassword = 'wrong_password';
        $credentials = new Credentials($email, $passwordHash);

        $verifyCallback = static fn (string $plain, string $hash): bool => 'correct_password' === $plain && $hash === $passwordHash;

        // Act
        $result = $credentials->verifyPassword($plainPassword, $verifyCallback);

        // Assert
        Assert::assertFalse($result);
    }

    #[Test]
    public function verify_password_calls_callback_with_correct_parameters(): void
    {
        // Arrange
        $email = 'test@example.com';
        $passwordHash = 'hashed_password_123';
        $plainPassword = 'test_password';
        $credentials = new Credentials($email, $passwordHash);

        $callbackCalled = false;
        $callbackParams = [];

        $verifyCallback = static function (string $plain, string $hash) use (&$callbackCalled, &$callbackParams): bool {
            $callbackCalled = true;
            $callbackParams = [$plain, $hash];

            return true;
        };

        // Act
        $credentials->verifyPassword($plainPassword, $verifyCallback);

        // Assert
        Assert::assertTrue($callbackCalled);
        Assert::assertSame($plainPassword, $callbackParams[0]);
        Assert::assertSame($passwordHash, $callbackParams[1]);
    }
}
