<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Domain\Event;

use App\Notification\Notification\Domain\Enum\NotificationTypeEnum;
use App\Notification\Notification\Domain\Event\NotificationCreated;
use App\Notification\Notification\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationCreated::class)]
final class NotificationCreatedTest extends TestCase
{
    #[Test]
    public function creates_event_and_exposes_properties(): void
    {
        // Arrange
        $notificationId = Id::fromString('550e8400-e29b-41d4-a716-446655440000');
        $recipient = EmailAddress::fromString('john.doe@example.com');
        $subject = 'Welcome';
        $body = 'Welcome to Refleet!';
        $type = NotificationTypeEnum::EMAIL;

        // Act
        $event = new NotificationCreated(
            notificationId: $notificationId,
            recipient: $recipient,
            subject: $subject,
            body: $body,
            type: $type,
        );

        // Assert
        Assert::assertSame($notificationId, $event->notificationId());
        Assert::assertSame($recipient, $event->recipient());
        Assert::assertSame($subject, $event->subject());
        Assert::assertSame($body, $event->body());
        Assert::assertSame($type, $event->type());
    }
}
