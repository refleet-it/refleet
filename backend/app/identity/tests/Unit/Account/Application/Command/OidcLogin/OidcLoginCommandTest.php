<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\OidcLogin;

use App\Identity\Account\Application\Command\OidcLogin\OidcLoginCommand;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OidcLoginCommand::class)]
final class OidcLoginCommandTest extends TestCase
{
    #[Test]
    public function carries_the_email_subject_and_marketing_consent(): void
    {
        // Act
        $command = new OidcLoginCommand(
            email: 'user@example.com',
            subject: 'dex-subject-123',
            marketingConsent: true,
        );

        // Assert
        Assert::assertSame('user@example.com', $command->email);
        Assert::assertSame('dex-subject-123', $command->subject);
        Assert::assertTrue($command->marketingConsent);
    }

    #[Test]
    public function defaults_marketing_consent_to_false(): void
    {
        // Act
        $command = new OidcLoginCommand(email: 'user@example.com', subject: 'dex-subject-456');

        // Assert
        Assert::assertFalse($command->marketingConsent);
    }

    #[Test]
    public function is_readonly_after_creation(): void
    {
        // Arrange
        $command = new OidcLoginCommand(email: 'user@example.com', subject: 'dex-subject-789');
        $thrown = null;

        // Act
        try {
            $command->subject = 'another-subject';
        } catch (\Throwable $throwable) {
            $thrown = $throwable;
        }

        // Assert
        Assert::assertInstanceOf(\Error::class, $thrown);
        Assert::assertStringContainsString('Cannot modify readonly property', (string) ($thrown?->getMessage() ?? ''));
        Assert::assertStringContainsString('OidcLoginCommand::$subject', (string) ($thrown?->getMessage() ?? ''));
    }
}
