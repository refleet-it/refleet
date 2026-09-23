<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Application\Command\CreateEmailNotification;

use App\Notification\Notification\Application\Command\CreateEmailNotification\CreateEmailNotificationCommand;
use App\Notification\Notification\Application\Command\CreateEmailNotification\CreateEmailNotificationHandler;
use App\Notification\Notification\Domain\Enum\NotificationStatusEnum;
use App\Notification\Notification\Domain\Enum\NotificationTypeEnum;
use App\Notification\Notification\Domain\Event\NotificationCreated;
use App\Notification\Notification\Domain\Model\Notification;
use App\Notification\Notification\Domain\Repository\NotificationRepositoryInterface;
use App\Notification\Notification\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateEmailNotificationHandler::class)]
#[UsesClass(CreateEmailNotificationCommand::class)]
#[UsesClass(Notification::class)]
#[UsesClass(NotificationTypeEnum::class)]
#[UsesClass(NotificationStatusEnum::class)]
#[UsesClass(NotificationCreated::class)]
#[UsesClass(EmailAddress::class)]
#[UsesClass(Id::class)]
final class CreateEmailNotificationHandlerTest extends TestCase
{
    private NotificationRepositoryInterface&MockObject $notifications;

    private CreateEmailNotificationHandler $handler;

    #[Test]
    public function saves_email_notification_with_provided_data(): void
    {
        // Arrange
        $id = Id::fromString('11111111-2222-3333-4444-555555555555');
        $email = 'user@example.com';
        $subject = 'Welcome';
        $body = 'Hello there!';

        $command = new CreateEmailNotificationCommand(
            id: $id,
            email: $email,
            subject: $subject,
            body: $body,
        );

        $this->notifications
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Notification $notification) use ($id, $email, $subject, $body): bool {
                // Assert entity fields
                Assert::assertSame($id->asString(), $notification->id()->asString());
                Assert::assertSame($email, $notification->recipient()->value());
                Assert::assertSame($subject, $notification->subject());
                Assert::assertSame($body, $notification->body());
                Assert::assertSame(NotificationTypeEnum::EMAIL, $notification->type());
                Assert::assertSame(NotificationStatusEnum::PENDING, $notification->status());

                // Assert domain event recorded
                $events = $notification->getRecordedDomainEvents();
                Assert::assertCount(1, $events);
                Assert::assertInstanceOf(NotificationCreated::class, $events[0]);
                /** @var NotificationCreated $event */
                $event = $events[0];
                Assert::assertSame($id->asString(), $event->notificationId()->asString());
                Assert::assertSame($email, $event->recipient()->value());
                Assert::assertSame($subject, $event->subject());
                Assert::assertSame($body, $event->body());
                Assert::assertSame(NotificationTypeEnum::EMAIL, $event->type());

                return true;
            }));

        // Act
        ($this->handler)($command);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->notifications = $this->createMock(NotificationRepositoryInterface::class);
        $this->handler = new CreateEmailNotificationHandler($this->notifications);
    }
}
