<?php

declare(strict_types=1);

namespace App\Notification\NotificationPreference\Application\Query\GetNotificationPreferences;

final readonly class GetNotificationPreferencesQuery
{
    public function __construct(
        public string $userId,
    ) {
    }
}
