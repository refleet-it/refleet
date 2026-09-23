<?php

declare(strict_types=1);

namespace App\Notification\NotificationPreference\Application\Query\GetNotificationPreferences;

use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Notification\NotificationPreference\Domain\Enum\NotificationPreferenceCatalog;
use App\Notification\NotificationPreference\Domain\Model\NotificationPreference;
use App\Notification\NotificationPreference\Domain\Repository\NotificationPreferenceRepositoryInterface;
use App\Shared\Domain\ValueObject\Id;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetNotificationPreferencesHandler
{
    public function __construct(
        private NotificationPreferenceRepositoryInterface $preferences,
    ) {
    }

    /**
     * @return NotificationPreferenceOverview[]
     */
    public function __invoke(GetNotificationPreferencesQuery $query): array
    {
        $userId = Id::fromString($query->userId);
        $saved = $this->preferences->findByUserId($userId);

        $byType = [];
        foreach ($saved as $preference) {
            $byType[$preference->notificationType()] = $preference;
        }

        $overviews = [];
        foreach (NotificationPreferenceCatalog::TYPES as $type => $label) {
            $preference = $byType[$type] ?? null;

            $overviews[] = new NotificationPreferenceOverview(
                notificationType: $type,
                label: $label,
                enabledChannels: $this->resolveEnabledChannels($preference),
            );
        }

        return $overviews;
    }

    /**
     * @return array<string>
     */
    private function resolveEnabledChannels(?NotificationPreference $preference): array
    {
        if (null === $preference) {
            return [NotificationChannelEnum::EMAIL->value, NotificationChannelEnum::IN_APP->value];
        }

        return $preference->isEnabled() ? $preference->enabledChannels() : [];
    }
}
