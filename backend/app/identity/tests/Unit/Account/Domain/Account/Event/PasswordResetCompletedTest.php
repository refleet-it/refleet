<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Event;

use App\Identity\Account\Domain\Account\Event\PasswordResetCompleted;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[CoversClass(PasswordResetCompleted::class)]
#[UsesClass(AccountId::class)]
final class PasswordResetCompletedTest extends TestCase
{
    #[Test]
    public function creates_event_and_exposes_properties(): void
    {
        // Arrange
        $accountId = AccountId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'user@example.com';

        // Act
        $event = new PasswordResetCompleted(
            accountId: $accountId,
            email: $email,
        );

        // Assert
        Assert::assertSame($accountId, $event->accountId());
        Assert::assertSame($email, $event->email());
    }

    #[Test]
    public function has_as_message_sync_attribute(): void
    {
        // Arrange & Act
        $reflection = new \ReflectionClass(PasswordResetCompleted::class);
        $attributes = $reflection->getAttributes(AsMessage::class);

        // Assert
        Assert::assertNotEmpty($attributes);
        $attribute = $attributes[0]->newInstance();
        Assert::assertSame('sync', $attribute->transport);
    }
}
