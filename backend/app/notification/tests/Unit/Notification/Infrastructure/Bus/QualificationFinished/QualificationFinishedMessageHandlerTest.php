<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Infrastructure\Bus\QualificationFinished;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Notification\Notification\Domain\Model\Notification;
use App\Notification\Notification\Domain\Repository\NotificationRepositoryInterface;
use App\Notification\Notification\Infrastructure\Bus\QualificationFinished\QualificationFinishedMessage;
use App\Notification\Notification\Infrastructure\Bus\QualificationFinished\QualificationFinishedMessageHandler;
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

#[CoversClass(QualificationFinishedMessageHandler::class)]
final class QualificationFinishedMessageHandlerTest extends TestCase
{
    use Factories;

    private NotificationRepositoryInterface&MockObject $notificationRepository;

    private NotificationPreferenceRepositoryInterface&Stub $preferenceRepository;

    private Environment&Stub $twig;

    private LoggerInterface&Stub $logger;

    private QualificationFinishedMessageHandler $handler;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function creates_email_and_in_app_notifications_by_default(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $message = $this->completedMessage($account->id()->asString(), $account->email());

        $this->twig->method('render')->willReturn('<p>done</p>');

        $savedNotifications = [];
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
        Assert::assertSame(NotificationChannelEnum::EMAIL, $savedNotifications[0]->channel());
        Assert::assertSame(NotificationChannelEnum::IN_APP, $savedNotifications[1]->channel());
    }

    #[Test]
    public function skips_a_channel_disabled_in_the_users_preferences(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $message = $this->completedMessage($account->id()->asString(), $account->email());

        $preference = NotificationPreference::create(
            id: Id::generate(),
            userId: Id::fromString($account->id()->asString()),
            notificationType: 'qualification_finished',
            enabledChannels: [NotificationChannelEnum::EMAIL->value],
        );

        $this->preferenceRepository = $this->createStub(NotificationPreferenceRepositoryInterface::class);
        $this->preferenceRepository->method('findByUserIdAndType')->willReturn($preference);
        $this->handler = new QualificationFinishedMessageHandler(
            notificationRepository: $this->notificationRepository,
            preferenceRepository: $this->preferenceRepository,
            twig: $this->twig,
            logger: $this->logger,
            frontendUrl: 'https://frontend.test',
        );

        $this->twig->method('render')->willReturn('<p>done</p>');

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
        Assert::assertSame(NotificationChannelEnum::EMAIL, $savedNotifications[0]->channel());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function uses_a_cancelled_subject_and_body_when_the_outcome_is_not_completed(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $message = new QualificationFinishedMessage(
            accountId: $account->id()->asString(),
            email: $account->email(),
            qualificationId: Id::generate()->asString(),
            title: 'Find projects depending on acme/legacy-lib',
            outcome: 'cancelled',
            cancelReason: 'Superseded by a newer qualification',
        );

        $this->twig->method('render')->willReturn('<p>cancelled</p>');

        $savedNotifications = [];
        $this->notificationRepository
            ->method('save')
            ->willReturnCallback(static function (Notification $notification) use (&$savedNotifications): void {
                $savedNotifications[] = $notification;
            });

        // Act
        ($this->handler)($message);

        // Assert
        Assert::assertCount(2, $savedNotifications);
        Assert::assertSame('Qualification cancelled: Find projects depending on acme/legacy-lib', $savedNotifications[0]->subject());
        Assert::assertSame('Qualification cancelled', $savedNotifications[1]->subject());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->notificationRepository = $this->createMock(NotificationRepositoryInterface::class);
        $this->preferenceRepository = $this->createStub(NotificationPreferenceRepositoryInterface::class);
        $this->preferenceRepository->method('findByUserIdAndType')->willReturn(null);
        $this->twig = $this->createStub(Environment::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->handler = new QualificationFinishedMessageHandler(
            notificationRepository: $this->notificationRepository,
            preferenceRepository: $this->preferenceRepository,
            twig: $this->twig,
            logger: $this->logger,
            frontendUrl: 'https://frontend.test',
        );
    }

    private function completedMessage(string $accountId, string $email): QualificationFinishedMessage
    {
        return new QualificationFinishedMessage(
            accountId: $accountId,
            email: $email,
            qualificationId: Id::generate()->asString(),
            title: 'Find projects depending on acme/legacy-lib',
            outcome: 'completed',
            cancelReason: null,
        );
    }
}
