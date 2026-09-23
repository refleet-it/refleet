<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\NotificationPreference\Application\Query\GetNotificationPreferences;

use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Notification\NotificationPreference\Application\Query\GetNotificationPreferences\GetNotificationPreferencesHandler;
use App\Notification\NotificationPreference\Application\Query\GetNotificationPreferences\GetNotificationPreferencesQuery;
use App\Notification\NotificationPreference\Domain\Model\NotificationPreference;
use App\Notification\NotificationPreference\Domain\Repository\NotificationPreferenceRepositoryInterface;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetNotificationPreferencesHandler::class)]
final class GetNotificationPreferencesHandlerTest extends TestCase
{
    private NotificationPreferenceRepositoryInterface&Stub $preferences;

    private GetNotificationPreferencesHandler $handler;

    #[Test]
    public function defaults_unsaved_types_to_all_channels_enabled(): void
    {
        // Arrange
        $this->preferences->method('findByUserId')->willReturn([]);

        // Act
        $result = ($this->handler)(new GetNotificationPreferencesQuery(Id::generate()->asString()));

        // Assert
        Assert::assertCount(3, $result);
        foreach ($result as $overview) {
            Assert::assertContains(NotificationChannelEnum::EMAIL->value, $overview->enabledChannels);
            Assert::assertContains(NotificationChannelEnum::IN_APP->value, $overview->enabledChannels);
        }
    }

    #[Test]
    public function reflects_a_saved_preference(): void
    {
        // Arrange
        $userId = Id::generate();
        $preference = NotificationPreference::create(
            id: Id::generate(),
            userId: $userId,
            notificationType: 'shift_finished',
            enabledChannels: [NotificationChannelEnum::EMAIL->value],
        );

        $this->preferences->method('findByUserId')->willReturn([$preference]);

        // Act
        $result = ($this->handler)(new GetNotificationPreferencesQuery($userId->asString()));

        // Assert
        $shiftFinished = \array_first(\array_filter(
            $result,
            static fn ($overview): bool => 'shift_finished' === $overview->notificationType,
        ));
        Assert::assertSame([NotificationChannelEnum::EMAIL->value], $shiftFinished->enabledChannels);
    }

    #[Test]
    public function a_disabled_preference_has_no_enabled_channels(): void
    {
        // Arrange
        $userId = Id::generate();
        $preference = NotificationPreference::create(
            id: Id::generate(),
            userId: $userId,
            notificationType: 'password_reset_completed',
        );
        $preference->disable();

        $this->preferences->method('findByUserId')->willReturn([$preference]);

        // Act
        $result = ($this->handler)(new GetNotificationPreferencesQuery($userId->asString()));

        // Assert
        $passwordResetCompleted = \array_first(\array_filter(
            $result,
            static fn ($overview): bool => 'password_reset_completed' === $overview->notificationType,
        ));
        Assert::assertSame([], $passwordResetCompleted->enabledChannels);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->preferences = $this->createStub(NotificationPreferenceRepositoryInterface::class);
        $this->handler = new GetNotificationPreferencesHandler($this->preferences);
    }
}
