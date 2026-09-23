<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Event;

use App\Identity\Account\Domain\Account\Event\PasswordResetEmailRequested;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordResetEmailRequested::class)]
final class PasswordResetEmailRequestedTest extends TestCase
{
    #[Test]
    public function creates_event_and_exposes_properties(): void
    {
        // Arrange
        $accountId = AccountId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'user@example.com';
        $resetToken = 'reset_token_123';
        $occurredAt = new \DateTimeImmutable('2024-01-01 12:00:00');

        // Act
        $event = new PasswordResetEmailRequested(
            accountId: $accountId,
            email: $email,
            resetToken: $resetToken,
            occurredAt: $occurredAt,
        );

        // Assert
        Assert::assertSame($accountId, $event->accountId());
        Assert::assertSame($email, $event->email());
        Assert::assertSame($resetToken, $event->resetToken());
        Assert::assertSame($occurredAt->getTimestamp(), $event->occurredAt()->getTimestamp());
        Assert::assertSame($accountId, $event->getAggregateId());
    }

    #[Test]
    public function creates_event_from_named_constructor(): void
    {
        // Arrange
        $accountId = AccountId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'user@example.com';
        $resetToken = 'reset_token_123';
        $occurredAt = new \DateTimeImmutable('2024-01-01 12:00:00');

        // Act
        $event = PasswordResetEmailRequested::fromPasswordResetRequested(
            accountId: $accountId,
            email: $email,
            resetToken: $resetToken,
            occurredAt: $occurredAt,
        );

        // Assert
        Assert::assertSame($accountId, $event->accountId());
        Assert::assertSame($email, $event->email());
        Assert::assertSame($resetToken, $event->resetToken());
        Assert::assertSame($occurredAt->getTimestamp(), $event->occurredAt()->getTimestamp());
        Assert::assertSame($accountId, $event->getAggregateId());
    }
}
