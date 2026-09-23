<?php

declare(strict_types=1);

namespace App\Notification\NotificationPreference\Application\Command\UpdateNotificationPreference;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class UpdateNotificationPreferenceCommand implements CommandInterface
{
    /**
     * @param array<string> $enabledChannels
     */
    public function __construct(
        public string $userId,
        public string $notificationType,
        public array $enabledChannels,
    ) {
    }
}
