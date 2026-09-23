<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Domain\Model;

use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Notification\Notification\Domain\Enum\NotificationStatusEnum;
use App\Notification\Notification\Domain\Enum\NotificationTypeEnum;
use App\Notification\Notification\Domain\Event\NotificationCreated;
use App\Notification\Notification\Domain\Model\Notification;
use App\Notification\Notification\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Notification::class)]
#[UsesClass(Id::class)]
#[UsesClass(EmailAddress::class)]
#[UsesClass(NotificationCreated::class)]
#[UsesClass(NotificationTypeEnum::class)]
#[UsesClass(NotificationStatusEnum::class)]
final class NotificationTest extends TestCase
{
    #[Test]
    public function create_builds_notification_and_records_event(): void
    {
        // Arrange
        $id = Id::fromString('11111111-2222-3333-4444-555555555555');
        $recipient = EmailAddress::fromString('john.doe@example.com');
        $subject = 'Subject';
        $body = 'Body text';

        // Act
        $notification = Notification::create(
            id: $id,
            recipient: $recipient,
            subject: $subject,
            body: $body,
            type: NotificationTypeEnum::EMAIL,
            channel: NotificationChannelEnum::EMAIL,
        );

        // Assert entity fields
        Assert::assertSame($id->asString(), $notification->id()->asString());
        Assert::assertSame($recipient->value(), $notification->recipient()->value());
        Assert::assertSame($subject, $notification->subject());
        Assert::assertSame($body, $notification->body());
        Assert::assertSame(NotificationTypeEnum::EMAIL, $notification->type());
        Assert::assertSame(NotificationStatusEnum::PENDING, $notification->status());
        Assert::assertInstanceOf(\DateTimeImmutable::class, $notification->createdAt());

        // Assert domain event
        $events = $notification->getRecordedDomainEvents();
        Assert::assertCount(1, $events);
        Assert::assertInstanceOf(NotificationCreated::class, $events[0]);
        /** @var NotificationCreated $event */
        $event = $events[0];
        Assert::assertSame($id->asString(), $event->notificationId()->asString());
        Assert::assertSame($recipient->value(), $event->recipient()->value());
        Assert::assertSame($subject, $event->subject());
        Assert::assertSame($body, $event->body());
        Assert::assertSame(NotificationTypeEnum::EMAIL, $event->type());

        // Ensure events are cleared
        Assert::assertSame([], $notification->getRecordedDomainEvents());
    }

    #[Test]
    public function create_with_status_sets_all_fields_and_records_event(): void
    {
        // Arrange
        $id = Id::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $recipient = EmailAddress::fromString('alice@example.com');
        $subject = 'Hello';
        $body = 'World';
        $status = NotificationStatusEnum::FAILED;
        $createdAt = new \DateTimeImmutable('2024-01-02 03:04:05');

        // Act
        $notification = Notification::createWithStatus(
            id: $id,
            recipient: $recipient,
            subject: $subject,
            body: $body,
            type: NotificationTypeEnum::SMS,
            channel: NotificationChannelEnum::IN_APP,
            priority: \App\Notification\Notification\Domain\Enum\NotificationPriorityEnum::INFO,
            status: $status,
            isMutable: true,
            userId: null,
            createdAt: $createdAt,
        );

        // Assert
        Assert::assertSame($id->asString(), $notification->id()->asString());
        Assert::assertSame($recipient->value(), $notification->recipient()->value());
        Assert::assertSame($subject, $notification->subject());
        Assert::assertSame($body, $notification->body());
        Assert::assertSame(NotificationTypeEnum::SMS, $notification->type());
        Assert::assertSame($status, $notification->status());
        Assert::assertSame($createdAt, $notification->createdAt());

        $events = $notification->getRecordedDomainEvents();
        Assert::assertCount(1, $events);
        Assert::assertInstanceOf(NotificationCreated::class, $events[0]);
    }

    #[Test]
    public function mark_as_sent_updates_status(): void
    {
        $notification = Notification::create(
            Id::fromString('11111111-2222-3333-4444-555555555556'),
            EmailAddress::fromString('user@example.com'),
            'S',
            'B',
            NotificationTypeEnum::EMAIL,
            NotificationChannelEnum::EMAIL,
        );

        $notification->markAsSent();

        Assert::assertSame(NotificationStatusEnum::SENT, $notification->status());
    }

    #[Test]
    public function mark_as_failed_updates_status(): void
    {
        $notification = Notification::create(
            Id::fromString('11111111-2222-3333-4444-555555555557'),
            EmailAddress::fromString('user@example.com'),
            'S',
            'B',
            NotificationTypeEnum::EMAIL,
            NotificationChannelEnum::EMAIL,
        );

        $notification->markAsFailed();

        Assert::assertSame(NotificationStatusEnum::FAILED, $notification->status());
    }
}
