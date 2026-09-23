<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\NotificationPreference\Domain\Model;

use App\Fixtures\Factory\Notification\NotificationPreferenceFactory;
use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Notification\NotificationPreference\Domain\Model\NotificationPreference;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(NotificationPreference::class)]
final class NotificationPreferenceTest extends TestCase
{
    use Factories;

    #[Test]
    public function create_uses_default_channels_and_initial_timestamps_when_channels_not_provided(): void
    {
        // Arrange
        $before = new \DateTimeImmutable();

        // Act
        $preference = NotificationPreferenceFactory::new()->withoutPersisting()->create([
            'enabledChannels' => [],
        ]);
        $after = new \DateTimeImmutable();

        // Assert
        Assert::assertSame(
            [NotificationChannelEnum::IN_APP->value, NotificationChannelEnum::EMAIL->value],
            $preference->enabledChannels(),
        );
        Assert::assertTrue($preference->isEnabled());
        Assert::assertTrue($preference->isChannelEnabled(NotificationChannelEnum::IN_APP));
        Assert::assertTrue($preference->isChannelEnabled(NotificationChannelEnum::EMAIL));
        Assert::assertGreaterThanOrEqual($before->getTimestamp(), $preference->createdAt()->getTimestamp());
        Assert::assertLessThanOrEqual($after->getTimestamp(), $preference->createdAt()->getTimestamp());
        Assert::assertSame($preference->createdAt()->getTimestamp(), $preference->updatedAt()->getTimestamp());
    }

    #[Test]
    public function is_channel_enabled_returns_false_when_preference_is_disabled_even_if_channel_exists(): void
    {
        // Arrange
        $preference = NotificationPreferenceFactory::new()->withoutPersisting()->create([
            'enabledChannels' => [NotificationChannelEnum::EMAIL->value],
            'isEnabled' => true,
        ]);

        // Act
        $preference->disable();

        // Assert
        Assert::assertFalse($preference->isEnabled());
        Assert::assertFalse($preference->isChannelEnabled(NotificationChannelEnum::EMAIL));
    }

    #[Test]
    public function update_channels_replaces_existing_channels_and_refreshes_updated_at(): void
    {
        // Arrange
        $preference = NotificationPreferenceFactory::new()->withoutPersisting()->create([
            'enabledChannels' => [NotificationChannelEnum::IN_APP->value],
        ]);
        $updatedAtBefore = $preference->updatedAt();

        // Act
        $preference->updateChannels([NotificationChannelEnum::EMAIL->value]);
        $updatedAtAfter = $preference->updatedAt();

        // Assert
        Assert::assertSame([NotificationChannelEnum::EMAIL->value], $preference->enabledChannels());
        Assert::assertFalse($preference->isChannelEnabled(NotificationChannelEnum::IN_APP));
        Assert::assertTrue($preference->isChannelEnabled(NotificationChannelEnum::EMAIL));
        Assert::assertGreaterThanOrEqual($updatedAtBefore->getTimestamp(), $updatedAtAfter->getTimestamp());
    }

    #[Test]
    public function enable_and_disable_toggle_state_and_keep_identity_values_stable(): void
    {
        // Arrange
        $preference = NotificationPreferenceFactory::new()->withoutPersisting()->create([
            'isEnabled' => false,
        ]);
        $id = $preference->id();
        $userId = $preference->userId();
        $type = $preference->notificationType();

        // Act
        $preference->enable();
        $enabledUpdatedAt = $preference->updatedAt();
        $preference->disable();
        $disabledUpdatedAt = $preference->updatedAt();

        // Assert
        Assert::assertFalse($preference->isEnabled());
        Assert::assertTrue($preference->id()->equals($id));
        Assert::assertTrue($preference->userId()->equals($userId));
        Assert::assertSame($type, $preference->notificationType());
        Assert::assertGreaterThanOrEqual($enabledUpdatedAt->getTimestamp(), $disabledUpdatedAt->getTimestamp());
    }
}
