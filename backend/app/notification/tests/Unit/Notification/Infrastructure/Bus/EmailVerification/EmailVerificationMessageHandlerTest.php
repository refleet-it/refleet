<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Infrastructure\Bus\EmailVerification;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Notification\Notification\Domain\Enum\NotificationPriorityEnum;
use App\Notification\Notification\Domain\Enum\NotificationStatusEnum;
use App\Notification\Notification\Domain\Enum\NotificationTypeEnum;
use App\Notification\Notification\Domain\Model\Notification;
use App\Notification\Notification\Domain\Repository\NotificationRepositoryInterface;
use App\Notification\Notification\Infrastructure\Bus\EmailVerification\EmailVerificationMessage;
use App\Notification\Notification\Infrastructure\Bus\EmailVerification\EmailVerificationMessageHandler;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(EmailVerificationMessageHandler::class)]
final class EmailVerificationMessageHandlerTest extends TestCase
{
    use Factories;

    private NotificationRepositoryInterface&MockObject $notificationRepository;

    private Environment&MockObject $twig;

    private LoggerInterface&MockObject $logger;

    private EmailVerificationMessageHandler $handler;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function creates_email_notification_with_expected_payload(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $message = new EmailVerificationMessage(
            accountId: $account->id()->asString(),
            email: $account->email(),
            verificationToken: 'verification +/?=%2B',
        );
        $expectedVerificationUrl = 'https://frontend.test/auth/verify-email?token='.$message->verificationToken;
        $emailBody = '<p>verify body</p>';
        $savedNotifications = [];

        $this->twig
            ->expects($this->once())
            ->method('render')
            ->with('notifications/email/email_verification.html.twig', ['verificationUrl' => $expectedVerificationUrl])
            ->willReturn($emailBody);

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
        $notification = $savedNotifications[0];
        Assert::assertSame($message->email, $notification->recipient()->value());
        Assert::assertSame('Confirm your email address - Refleet', $notification->subject());
        Assert::assertSame($emailBody, $notification->body());
        Assert::assertSame(NotificationTypeEnum::EMAIL, $notification->type());
        Assert::assertSame(NotificationChannelEnum::EMAIL, $notification->channel());
        Assert::assertSame(NotificationPriorityEnum::INFO, $notification->priority());
        Assert::assertSame(NotificationStatusEnum::PENDING, $notification->status());
        Assert::assertFalse($notification->isMutable());
        Assert::assertSame($message->accountId, $notification->userId()?->asString());
    }

    #[Test]
    public function rethrows_when_twig_rendering_fails(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $message = new EmailVerificationMessage(
            accountId: $account->id()->asString(),
            email: $account->email(),
            verificationToken: 'token',
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
                'Failed to create notification for EmailVerification message',
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
    public function rethrows_when_saving_notification_fails(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $message = new EmailVerificationMessage(
            accountId: $account->id()->asString(),
            email: $account->email(),
            verificationToken: 'token',
        );
        $exception = new \RuntimeException('Repository failed');
        $savedNotifications = [];

        $this->twig
            ->expects($this->once())
            ->method('render')
            ->willReturn('email body');

        $this->notificationRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (Notification $notification) use (&$savedNotifications, $exception): void {
                $savedNotifications[] = $notification;
                throw $exception;
            });

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to create notification for EmailVerification message',
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
        Assert::assertCount(1, $savedNotifications);
        Assert::assertSame(NotificationTypeEnum::EMAIL, $savedNotifications[0]->type());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->notificationRepository = $this->createMock(NotificationRepositoryInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new EmailVerificationMessageHandler(
            notificationRepository: $this->notificationRepository,
            twig: $this->twig,
            logger: $this->logger,
            frontendUrl: 'https://frontend.test',
        );
    }
}
