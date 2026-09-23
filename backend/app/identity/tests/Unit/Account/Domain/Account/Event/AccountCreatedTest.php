<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Event;

use App\Identity\Account\Domain\Account\Event\AccountCreated;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountCreated::class)]
final class AccountCreatedTest extends TestCase
{
    #[Test]
    public function creates_event_and_exposes_properties(): void
    {
        // Arrange
        $accountId = AccountId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'user@example.com';

        // Act
        $event = new AccountCreated(
            accountId: $accountId,
            email: $email,
        );

        // Assert
        Assert::assertSame($accountId, $event->accountId());
        Assert::assertSame($email, $event->email());
    }
}
