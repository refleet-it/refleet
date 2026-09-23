<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Infrastructure\Bus\ShiftFinished;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Notification\Notification\Domain\Model\Notification;
use App\Notification\Notification\Domain\Repository\NotificationRepositoryInterface;
use App\Notification\Notification\Infrastructure\Bus\ShiftFinished\ShiftFinishedMessage;
use App\Notification\Notification\Infrastructure\Bus\ShiftFinished\ShiftFinishedMessageHandler;
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

#[CoversClass(ShiftFinishedMessageHandler::class)]
final class ShiftFinishedMessageHandlerTest extends TestCase
{
    use Factories;

    private NotificationRepositoryInterface&MockObject $notificationRepository;

    private NotificationPreferenceRepositoryInterface&Stub $preferenceRepository;

    private Environment&Stub $twig;

    private LoggerInterface&Stub $logger;

    private ShiftFinishedMessageHandler $handler;

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
            notificationType: 'shift_finished',
            enabledChannels: [NotificationChannelEnum::EMAIL->value],
        );

        $this->preferenceRepository = $this->createStub(NotificationPreferenceRepositoryInterface::class);
        $this->preferenceRepository->method('findByUserIdAndType')->willReturn($preference);
        $this->handler = new ShiftFinishedMessageHandler(
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
        $message = new ShiftFinishedMessage(
            accountId: $account->id()->asString(),
            email: $account->email(),
            shiftId: Id::generate()->asString(),
            title: 'Bump acme/legacy-lib to v3',
            outcome: 'cancelled',
            cancelReason: 'Superseded by a newer shift',
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
        Assert::assertSame('Shift cancelled: Bump acme/legacy-lib to v3', $savedNotifications[0]->subject());
        Assert::assertSame('Shift cancelled', $savedNotifications[1]->subject());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->notificationRepository = $this->createMock(NotificationRepositoryInterface::class);
        $this->preferenceRepository = $this->createStub(NotificationPreferenceRepositoryInterface::class);
        $this->preferenceRepository->method('findByUserIdAndType')->willReturn(null);
        $this->twig = $this->createStub(Environment::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->handler = new ShiftFinishedMessageHandler(
            notificationRepository: $this->notificationRepository,
            preferenceRepository: $this->preferenceRepository,
            twig: $this->twig,
            logger: $this->logger,
            frontendUrl: 'https://frontend.test',
        );
    }

    private function completedMessage(string $accountId, string $email): ShiftFinishedMessage
    {
        return new ShiftFinishedMessage(
            accountId: $accountId,
            email: $email,
            shiftId: Id::generate()->asString(),
            title: 'Bump acme/legacy-lib to v3',
            outcome: 'completed',
            cancelReason: null,
        );
    }
}
