<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\GoogleOAuthLogin;

use App\Identity\Account\Application\Command\GoogleOAuthLogin\GoogleOAuthLoginCommand;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GoogleOAuthLoginCommand::class)]
final class GoogleOAuthLoginCommandTest extends TestCase
{
    #[Test]
    public function creates_command_with_email_google_id_and_marketing_consent(): void
    {
        // Arrange
        $email = 'user@example.com';
        $googleId = 'google-user-123';

        // Act
        $command = new GoogleOAuthLoginCommand(
            email: $email,
            googleId: $googleId,
            marketingConsent: true,
        );

        // Assert
        Assert::assertSame($email, $command->email);
        Assert::assertSame($googleId, $command->googleId);
        Assert::assertTrue($command->marketingConsent);
    }

    #[Test]
    public function defaults_marketing_consent_to_false_when_not_provided(): void
    {
        // Arrange
        $email = 'user@example.com';
        $googleId = 'google-user-456';

        // Act
        $command = new GoogleOAuthLoginCommand(
            email: $email,
            googleId: $googleId,
        );

        // Assert
        Assert::assertSame($email, $command->email);
        Assert::assertSame($googleId, $command->googleId);
        Assert::assertFalse($command->marketingConsent);
    }

    #[Test]
    public function preserves_empty_email_and_google_id_without_validation(): void
    {
        // Arrange
        $email = '';
        $googleId = '';

        // Act
        $command = new GoogleOAuthLoginCommand(
            email: $email,
            googleId: $googleId,
            marketingConsent: false,
        );

        // Assert
        Assert::assertSame('', $command->email);
        Assert::assertSame('', $command->googleId);
        Assert::assertFalse($command->marketingConsent);
    }

    #[Test]
    public function rejects_non_string_email(): void
    {
        // Arrange
        $email = \json_decode('null');
        $exception = null;

        // Act
        try {
            new GoogleOAuthLoginCommand(
                email: $email,
                googleId: 'google-user-789',
            );
        } catch (\Throwable $throwable) {
            $exception = $throwable;
        }

        // Assert
        Assert::assertInstanceOf(\TypeError::class, $exception);
    }

    #[Test]
    public function command_is_readonly_after_creation(): void
    {
        // Arrange
        $command = new GoogleOAuthLoginCommand(
            email: 'user@example.com',
            googleId: 'google-user-111',
        );
        $thrown = null;

        // Act
        try {
            $command->googleId = 'google-user-updated';
        } catch (\Throwable $throwable) {
            $thrown = $throwable;
        }

        // Assert
        Assert::assertInstanceOf(\Error::class, $thrown);
        Assert::assertStringContainsString('Cannot modify readonly property', (string) ($thrown?->getMessage() ?? ''));
        Assert::assertStringContainsString('GoogleOAuthLoginCommand::$googleId', (string) ($thrown?->getMessage() ?? ''));
    }
}
