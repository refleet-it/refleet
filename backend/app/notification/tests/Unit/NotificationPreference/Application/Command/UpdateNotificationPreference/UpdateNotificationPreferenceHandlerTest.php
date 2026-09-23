<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\NotificationPreference\Application\Command\UpdateNotificationPreference;

use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Notification\NotificationPreference\Application\Command\UpdateNotificationPreference\UpdateNotificationPreferenceCommand;
use App\Notification\NotificationPreference\Application\Command\UpdateNotificationPreference\UpdateNotificationPreferenceHandler;
use App\Notification\NotificationPreference\Domain\Exception\UnknownNotificationTypeException;
use App\Notification\NotificationPreference\Domain\Model\NotificationPreference;
use App\Notification\NotificationPreference\Domain\Repository\NotificationPreferenceRepositoryInterface;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateNotificationPreferenceHandler::class)]
final class UpdateNotificationPreferenceHandlerTest extends TestCase
{
    private NotificationPreferenceRepositoryInterface&MockObject $preferences;

    private UpdateNotificationPreferenceHandler $handler;

    #[Test]
    public function creates_a_preference_when_none_exists_yet(): void
    {
        // Arrange
        $userId = Id::generate();
        $this->preferences->method('findByUserIdAndType')->willReturn(null);

        $saved = null;
        $this->preferences
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (NotificationPreference $preference) use (&$saved): void {
                $saved = $preference;
            });

        // Act
        ($this->handler)(new UpdateNotificationPreferenceCommand(
            userId: $userId->asString(),
            notificationType: 'shift_finished',
            enabledChannels: [NotificationChannelEnum::EMAIL->value],
        ));

        // Assert
        Assert::assertNotNull($saved);
        Assert::assertSame([NotificationChannelEnum::EMAIL->value], $saved->enabledChannels());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function turning_off_every_channel_on_first_save_actually_disables_it(): void
    {
        // Arrange
        $this->preferences->method('findByUserIdAndType')->willReturn(null);

        $saved = null;
        $this->preferences
            ->method('save')
            ->willReturnCallback(static function (NotificationPreference $preference) use (&$saved): void {
                $saved = $preference;
            });

        // Act
        ($this->handler)(new UpdateNotificationPreferenceCommand(
            userId: Id::generate()->asString(),
            notificationType: 'shift_finished',
            enabledChannels: [],
        ));

        // Assert
        Assert::assertSame([], $saved->enabledChannels());
    }

    #[Test]
    public function updates_an_existing_preference(): void
    {
        // Arrange
        $userId = Id::generate();
        $preference = NotificationPreference::create(
            id: Id::generate(),
            userId: $userId,
            notificationType: 'password_reset_completed',
        );

        $this->preferences->method('findByUserIdAndType')->willReturn($preference);
        $this->preferences->expects($this->once())->method('update')->with($preference);
        $this->preferences->expects($this->never())->method('save');

        // Act
        ($this->handler)(new UpdateNotificationPreferenceCommand(
            userId: $userId->asString(),
            notificationType: 'password_reset_completed',
            enabledChannels: [NotificationChannelEnum::IN_APP->value],
        ));

        // Assert
        Assert::assertSame([NotificationChannelEnum::IN_APP->value], $preference->enabledChannels());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function rejects_an_unknown_notification_type(): void
    {
        $this->expectException(UnknownNotificationTypeException::class);

        ($this->handler)(new UpdateNotificationPreferenceCommand(
            userId: Id::generate()->asString(),
            notificationType: 'not_a_real_type',
            enabledChannels: [],
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->preferences = $this->createMock(NotificationPreferenceRepositoryInterface::class);
        $this->handler = new UpdateNotificationPreferenceHandler($this->preferences);
    }
}
