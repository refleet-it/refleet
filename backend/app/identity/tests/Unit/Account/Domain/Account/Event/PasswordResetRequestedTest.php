<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Event;

use App\Identity\Account\Domain\Account\Event\PasswordResetRequested;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordResetRequested::class)]
final class PasswordResetRequestedTest extends TestCase
{
    #[Test]
    public function creates_event_and_exposes_properties(): void
    {
        // Arrange
        $accountId = AccountId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'user@example.com';
        $resetToken = 'reset-token-123';

        // Act
        $event = new PasswordResetRequested(
            accountId: $accountId,
            email: $email,
            resetToken: $resetToken,
        );

        // Assert
        Assert::assertSame($accountId, $event->accountId());
        Assert::assertSame($email, $event->email());
        Assert::assertSame($resetToken, $event->resetToken());
    }
}
