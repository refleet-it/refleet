<?php

declare(strict_types=1);

namespace App\Notification\Notification\Domain\Enum;

enum NotificationPriorityEnum: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';
}
