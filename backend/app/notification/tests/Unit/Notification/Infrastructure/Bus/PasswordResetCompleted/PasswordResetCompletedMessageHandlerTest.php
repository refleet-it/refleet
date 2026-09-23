<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Infrastructure\Bus\PasswordResetCompleted;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Notification\Notification\Domain\Enum\NotificationPriorityEnum;
use App\Notification\Notification\Domain\Enum\NotificationStatusEnum;
use App\Notification\Notification\Domain\Enum\NotificationTypeEnum;
use App\Notification\Notification\Domain\Model\Notification;
use App\Notification\Notification\Domain\Repository\NotificationRepositoryInterface;
use App\Notification\Notification\Infrastructure\Bus\PasswordResetCompleted\PasswordResetCompletedMessage;
use App\Notification\Notification\Infrastructure\Bus\PasswordResetCompleted\PasswordResetCompletedMessageHandler;
use App\Notification\NotificationPreference\Domain\Model\NotificationPreference;
use App\Notification\NotificationPreference\Domain\Repository\NotificationPreferenceRepositoryInterface;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(PasswordResetCompletedMessageHandler::class)]
final class PasswordResetCompletedMessageHandlerTest extends TestCase
{
    use Factories;

    private NotificationRepositoryInterface&MockObject $notificationRepository;

    private NotificationPreferenceRepositoryInterface&Stub $preferenceRepository;

    private Environment&MockObject $twig;

    private LoggerInterface&MockObject $logger;

    private PasswordResetCompletedMessageHandler $handler;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function creates_email_and_in_app_notifications_with_expected_payload(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $message = new PasswordResetCompletedMessage(
            accountId: $account->id()->asString(),
            email: $account->email(),
        );
        $emailBody = '<p>password reset completed</p>';
        $savedNotifications = [];

        $this->twig
            ->expects($this->once())
            ->method('render')
            ->with('notifications/email/password_reset_confirmation.html.twig', ['frontendUrl' => 'https://frontend.test'])
            ->willReturn($emailBody);

        $this->notificationRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(static function (Notification $notification) use (&$savedNotifications): void {
                $savedNotifications[] = $notification;
            });

        // Act
        ($this->handler)($message);

        // Assert
        Assert::assertCount(2, $savedNotifications);

        $emailNotification = $savedNotifications[0];
        Assert::assertSame($message->email, $emailNotification->recipient()->value());
        Assert::assertSame('Password Changed - Refleet', $emailNotification->subject());
        Assert::assertSame($emailBody, $emailNotification->body());
        Assert::assertSame(NotificationTypeEnum::EMAIL, $emailNotification->type());
        Assert::assertSame(NotificationChannelEnum::EMAIL, $emailNotification->channel());
        Assert::assertSame(NotificationPriorityEnum::INFO, $emailNotification->priority());
        Assert::assertSame(NotificationStatusEnum::PENDING, $emailNotification->status());
        Assert::assertFalse($emailNotification->isMutable());
        Assert::assertSame($message->accountId, $emailNotification->userId()?->asString());

        $inAppNotification = $savedNotifications[1];
        Assert::assertSame($message->email, $inAppNotification->recipient()->value());
        Assert::assertSame('Password Changed', $inAppNotification->subject());
        Assert::assertSame("Your password has been changed successfully. If this wasn't you, contact us immediately.", $inAppNotification->body());
        Assert::assertSame(NotificationTypeEnum::PUSH, $inAppNotification->type());
        Assert::assertSame(NotificationChannelEnum::IN_APP, $inAppNotification->channel());
        Assert::assertSame(NotificationPriorityEnum::WARNING, $inAppNotification->priority());
        Assert::assertSame(NotificationStatusEnum::PENDING, $inAppNotification->status());
        Assert::assertTrue($inAppNotification->isMutable());
        Assert::assertSame($message->accountId, $inAppNotification->userId()?->asString());
        Assert::assertNotSame($emailNotification->id()->asString(), $inAppNotification->id()->asString());
    }

    #[Test]
    public function rethrows_when_twig_rendering_fails(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $message = new PasswordResetCompletedMessage(
            accountId: $account->id()->asString(),
            email: $account->email(),
        );
        $exception = new \RuntimeException('Twig failed');

        $this->twig
            ->expects($this->once())
            ->method('render')
            ->willThrowException($exception);

        $this->notificationRepository
            ->expects($this->never())
            ->method('save');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to create notifications for PasswordResetCompleted message',
                [
                    'accountId' => $message->accountId,
                    'error' => $exception->getMessage(),
                ],
            );

        // Act
        $thrown = null;
        try {
            ($this->handler)($message);
        } catch (\RuntimeException $runtimeException) {
            $thrown = $runtimeException;
        }

        // Assert
        Assert::assertSame($exception, $thrown);
    }

    #[Test]
    public function rethrows_when_saving_second_notification_fails(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $message = new PasswordResetCompletedMessage(
            accountId: $account->id()->asString(),
            email: $account->email(),
        );
        $exception = new \RuntimeException('Repository failed');
        $savedNotifications = [];

        $this->twig
            ->expects($this->once())
            ->method('render')
            ->willReturn('email body');

        $this->notificationRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(static function (Notification $notification) use (&$savedNotifications, $exception): void {
                $savedNotifications[] = $notification;
                if (2 === \count($savedNotifications)) {
                    throw $exception;
                }
            });

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to create notifications for PasswordResetCompleted message',
                [
                    'accountId' => $message->accountId,
                    'error' => $exception->getMessage(),
                ],
            );

        // Act
        $thrown = null;
        try {
            ($this->handler)($message);
        } catch (\RuntimeException $runtimeException) {
            $thrown = $runtimeException;
        }

        // Assert
        Assert::assertSame($exception, $thrown);
        Assert::assertCount(2, $savedNotifications);
        Assert::assertSame(NotificationTypeEnum::EMAIL, $savedNotifications[0]->type());
        Assert::assertSame(NotificationTypeEnum::PUSH, $savedNotifications[1]->type());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function skips_a_channel_disabled_in_the_users_preferences(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $message = new PasswordResetCompletedMessage(
            accountId: $account->id()->asString(),
            email: $account->email(),
        );

        $preference = NotificationPreference::create(
            id: Id::generate(),
            userId: Id::fromString($account->id()->asString()),
            notificationType: 'password_reset_completed',
            enabledChannels: [NotificationChannelEnum::IN_APP->value],
        );

        $this->preferenceRepository = $this->createStub(NotificationPreferenceRepositoryInterface::class);
        $this->preferenceRepository->method('findByUserIdAndType')->willReturn($preference);
        $this->handler = new PasswordResetCompletedMessageHandler(
            notificationRepository: $this->notificationRepository,
            preferenceRepository: $this->preferenceRepository,
            twig: $this->twig,
            logger: $this->logger,
            frontendUrl: 'https://frontend.test',
        );

        $savedNotifications = [];
        $this->notificationRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (Notification $notification) use (&$savedNotifications): void {
                $savedNotifications[] = $notification;
            });

        // Act
        ($this->handler)($message);

        // Assert
        Assert::assertCount(1, $savedNotifications);
        Assert::assertSame(NotificationChannelEnum::IN_APP, $savedNotifications[0]->channel());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->notificationRepository = $this->createMock(NotificationRepositoryInterface::class);
        $this->preferenceRepository = $this->createStub(NotificationPreferenceRepositoryInterface::class);
        $this->preferenceRepository->method('findByUserIdAndType')->willReturn(null);
        $this->twig = $this->createMock(Environment::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new PasswordResetCompletedMessageHandler(
            notificationRepository: $this->notificationRepository,
            preferenceRepository: $this->preferenceRepository,
            twig: $this->twig,
            logger: $this->logger,
            frontendUrl: 'https://frontend.test',
        );
    }
}
