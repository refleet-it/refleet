<?php

declare(strict_types=1);

namespace App\Notification\Notification\Domain\Enum;

enum NotificationTypeEnum: string
{
    public function isEmail(): bool
    {
        return self::EMAIL === $this;
    }

    case EMAIL = 'email';
    case SMS = 'sms';
    case PUSH = 'push';
    case SLACK = 'slack';
}
