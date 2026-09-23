<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Event;

use App\Identity\Account\Domain\Account\Event\PasswordResetConfirmationEmailRequested;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordResetConfirmationEmailRequested::class)]
final class PasswordResetConfirmationEmailRequestedTest extends TestCase
{
    #[Test]
    public function creates_event_and_exposes_properties(): void
    {
        // Arrange
        $accountId = AccountId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'user@example.com';
        $occurredAt = new \DateTimeImmutable('2024-01-01 12:00:00');

        // Act
        $event = new PasswordResetConfirmationEmailRequested(
            accountId: $accountId,
            email: $email,
            occurredAt: $occurredAt,
        );

        // Assert
        Assert::assertSame($accountId, $event->accountId());
        Assert::assertSame($email, $event->email());
        Assert::assertSame($occurredAt->getTimestamp(), $event->occurredAt()->getTimestamp());
        Assert::assertSame($accountId, $event->getAggregateId());
    }

    #[Test]
    public function creates_event_from_password_reset_named_constructor(): void
    {
        // Arrange
        $accountId = AccountId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'user@example.com';
        $occurredAt = new \DateTimeImmutable('2024-01-01 12:00:00');

        // Act
        $event = PasswordResetConfirmationEmailRequested::fromPasswordReset(
            accountId: $accountId,
            email: $email,
            occurredAt: $occurredAt,
        );

        // Assert
        Assert::assertSame($accountId, $event->accountId());
        Assert::assertSame($email, $event->email());
        Assert::assertSame($occurredAt->getTimestamp(), $event->occurredAt()->getTimestamp());
        Assert::assertSame($accountId, $event->getAggregateId());
    }

    #[Test]
    public function creates_event_from_password_reset_completed_named_constructor(): void
    {
        // Arrange
        $accountId = AccountId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'user@example.com';
        $occurredAt = new \DateTimeImmutable('2024-01-01 12:00:00');

        // Act
        $event = PasswordResetConfirmationEmailRequested::fromPasswordResetCompleted(
            accountId: $accountId,
            email: $email,
            occurredAt: $occurredAt,
        );

        // Assert
        Assert::assertSame($accountId, $event->accountId());
        Assert::assertSame($email, $event->email());
        Assert::assertSame($occurredAt->getTimestamp(), $event->occurredAt()->getTimestamp());
        Assert::assertSame($accountId, $event->getAggregateId());
    }

    #[Test]
    public function creates_event_from_password_changed_named_constructor(): void
    {
        // Arrange
        $accountId = AccountId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'user@example.com';
        $occurredAt = new \DateTimeImmutable('2024-01-01 12:00:00');

        // Act
        $event = PasswordResetConfirmationEmailRequested::fromPasswordChanged(
            accountId: $accountId,
            email: $email,
            occurredAt: $occurredAt,
        );

        // Assert
        Assert::assertSame($accountId, $event->accountId());
        Assert::assertSame($email, $event->email());
        Assert::assertSame($occurredAt->getTimestamp(), $event->occurredAt()->getTimestamp());
        Assert::assertSame($accountId, $event->getAggregateId());
    }
}
