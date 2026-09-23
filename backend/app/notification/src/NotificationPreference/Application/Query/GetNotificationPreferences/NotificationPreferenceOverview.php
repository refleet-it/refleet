<?php

declare(strict_types=1);

namespace App\Notification\NotificationPreference\Application\Query\GetNotificationPreferences;

final readonly class NotificationPreferenceOverview
{
    /**
     * @param array<string> $enabledChannels
     */
    public function __construct(
        public string $notificationType,
        public string $label,
        public array $enabledChannels,
    ) {
    }
}
