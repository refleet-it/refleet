<?php

declare(strict_types=1);

namespace App\Notification\Notification\Domain\Enum;

enum NotificationChannelEnum: string
{
    case IN_APP = 'in_app';
    case EMAIL = 'email';
    case PUSH = 'push';
}
