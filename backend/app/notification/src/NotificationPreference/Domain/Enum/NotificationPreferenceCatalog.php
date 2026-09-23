<?php

declare(strict_types=1);

namespace App\Notification\NotificationPreference\Domain\Enum;

/**
 * The notification types a user can toggle. Deliberately excludes transactional/security
 * messages (email_verification, password_reset) that aren't safe to opt out of.
 */
final class NotificationPreferenceCatalog
{
    public const array TYPES = [
        'password_reset_completed' => 'Password Changed',
        'shift_finished' => 'Shift Finished',
        'qualification_finished' => 'Qualification Finished',
    ];

    private function __construct()
    {
    }

    public static function isKnownType(string $notificationType): bool
    {
        return \array_key_exists($notificationType, self::TYPES);
    }

    public static function label(string $notificationType): string
    {
        return self::TYPES[$notificationType] ?? $notificationType;
    }
}
