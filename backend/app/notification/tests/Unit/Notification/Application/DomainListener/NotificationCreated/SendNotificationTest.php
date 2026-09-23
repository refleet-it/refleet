<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Application\DomainListener\NotificationCreated;

use App\Fixtures\Factory\Notification\NotificationFactory;
use App\Notification\Notification\Application\DomainListener\NotificationCreated\SendNotification;
use App\Notification\Notification\Domain\Enum\NotificationStatusEnum;
use App\Notification\Notification\Domain\Enum\NotificationTypeEnum;
use App\Notification\Notification\Domain\Event\NotificationCreated;
use App\Notification\Notification\Domain\Model\Notification;
use App\Notification\Notification\Domain\Repository\NotificationRepositoryInterface;
use App\Notification\Notification\Infrastructure\Service\EmailNotificationService;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(SendNotification::class)]
#[UsesClass(Notification::class)]
#[UsesClass(NotificationCreated::class)]
#[UsesClass(NotificationTypeEnum::class)]
#[UsesClass(NotificationStatusEnum::class)]
final class SendNotificationTest extends TestCase
{
    use Factories;

    private NotificationRepositoryInterface&MockObject $notifications;

    private MailerInterface&MockObject $mailer;

    private LoggerInterface&MockObject $handlerLogger;

    private SendNotification $handler;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function logs_error_and_stops_when_notification_is_missing(): void
    {
        // Arrange
        $notification = NotificationFactory::new()->withoutPersisting()->create();
        $event = new NotificationCreated(
            $notification->id(),
            $notification->recipient(),
            $notification->subject(),
            $notification->body(),
            $notification->type(),
        );

        $this->notifications
            ->expects($this->once())
            ->method('findById')
            ->with($event->notificationId())
            ->willReturn(null);

        $this->notifications
            ->expects($this->never())
            ->method('save');

        $this->handlerLogger
            ->expects($this->once())
            ->method('error')
            ->with('Notification not found for sending', [
                'notificationId' => $event->notificationId()->asString(),
            ]);

        // Act
        ($this->handler)($event);

        // Assert
        Assert::assertTrue(true);
    }

    #[Test]
    public function marks_notification_as_sent_and_saves_when_email_is_sent_successfully(): void
    {
        // Arrange
        $notification = NotificationFactory::new()->withoutPersisting()->create();
        $event = new NotificationCreated(
            $notification->id(),
            $notification->recipient(),
            $notification->subject(),
            $notification->body(),
            NotificationTypeEnum::EMAIL,
        );

        $this->notifications
            ->expects($this->once())
            ->method('findById')
            ->with($event->notificationId())
            ->willReturn($notification);

        $this->mailer
            ->expects($this->once())
            ->method('send');

        $this->notifications
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Notification $saved): bool {
                Assert::assertSame(NotificationStatusEnum::SENT, $saved->status());

                return true;
            }));

        $this->handlerLogger
            ->expects($this->once())
            ->method('info')
            ->with('Email notification processed', $this->callback(static function (array $context) use ($event): bool {
                Assert::assertSame($event->notificationId()->asString(), $context['notificationId']);
                Assert::assertSame($event->recipient()->value(), $context['recipient']);
                Assert::assertTrue($context['success']);

                return true;
            }));

        // Act
        ($this->handler)($event);

        // Assert
        Assert::assertSame(NotificationStatusEnum::SENT, $notification->status());
    }

    #[Test]
    public function marks_notification_as_failed_when_email_service_returns_false(): void
    {
        // Arrange
        $notification = NotificationFactory::new()->withoutPersisting()->create();
        $event = new NotificationCreated(
            $notification->id(),
            $notification->recipient(),
            $notification->subject(),
            $notification->body(),
            NotificationTypeEnum::EMAIL,
        );

        $this->notifications
            ->expects($this->once())
            ->method('findById')
            ->with($event->notificationId())
            ->willReturn($notification);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willThrowException(new TransportException('SMTP unavailable'));

        $this->notifications
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Notification $saved): bool {
                Assert::assertSame(NotificationStatusEnum::FAILED, $saved->status());

                return true;
            }));

        $this->handlerLogger
            ->expects($this->once())
            ->method('info')
            ->with('Email notification processed', $this->callback(static function (array $context) use ($event): bool {
                Assert::assertSame($event->notificationId()->asString(), $context['notificationId']);
                Assert::assertSame($event->recipient()->value(), $context['recipient']);
                Assert::assertFalse($context['success']);

                return true;
            }));

        // Act
        ($this->handler)($event);

        // Assert
        Assert::assertSame(NotificationStatusEnum::FAILED, $notification->status());
    }

    #[Test]
    public function does_not_send_or_save_for_non_email_notification_type(): void
    {
        // Arrange
        $notification = NotificationFactory::new()->withoutPersisting()->create([
            'type' => NotificationTypeEnum::SMS,
        ]);
        $event = new NotificationCreated(
            $notification->id(),
            $notification->recipient(),
            $notification->subject(),
            $notification->body(),
            NotificationTypeEnum::SMS,
        );

        $this->notifications
            ->expects($this->once())
            ->method('findById')
            ->with($event->notificationId())
            ->willReturn($notification);

        $this->mailer
            ->expects($this->never())
            ->method('send');

        $this->notifications
            ->expects($this->never())
            ->method('save');

        $this->handlerLogger
            ->expects($this->once())
            ->method('info')
            ->with('Notification type not supported yet', [
                'notificationId' => $event->notificationId()->asString(),
                'type' => NotificationTypeEnum::SMS->value,
            ]);

        // Act
        ($this->handler)($event);

        // Assert
        Assert::assertSame(NotificationStatusEnum::PENDING, $notification->status());
    }

    #[Test]
    public function marks_notification_as_failed_and_logs_error_when_unexpected_exception_occurs(): void
    {
        // Arrange
        $notification = NotificationFactory::new()->withoutPersisting()->create();
        $event = new NotificationCreated(
            $notification->id(),
            $notification->recipient(),
            $notification->subject(),
            $notification->body(),
            NotificationTypeEnum::EMAIL,
        );

        $this->notifications
            ->expects($this->once())
            ->method('findById')
            ->with($event->notificationId())
            ->willReturn($notification);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('Unexpected failure'));

        $this->notifications
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Notification $saved): bool {
                Assert::assertSame(NotificationStatusEnum::FAILED, $saved->status());

                return true;
            }));

        $this->handlerLogger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to process notification', $this->callback(static function (array $context) use ($event): bool {
                Assert::assertSame($event->notificationId()->asString(), $context['notificationId']);
                Assert::assertSame('Unexpected failure', $context['error']);

                return true;
            }));

        // Act
        ($this->handler)($event);

        // Assert
        Assert::assertSame(NotificationStatusEnum::FAILED, $notification->status());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->notifications = $this->createMock(NotificationRepositoryInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->handlerLogger = $this->createMock(LoggerInterface::class);

        $emailNotificationService = new EmailNotificationService(
            mailer: $this->mailer,
            twig: $this->createStub(Environment::class),
            logger: $this->createStub(LoggerInterface::class),
            fromEmail: 'noreply@refleet.test',
            fromName: 'Refleet',
            replyToEmail: 'support@refleet.test',
        );

        $this->handler = new SendNotification(
            notificationRepository: $this->notifications,
            emailNotificationService: $emailNotificationService,
            logger: $this->handlerLogger,
        );
    }
}
