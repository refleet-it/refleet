<?php

declare(strict_types=1);

namespace App\Notification\Notification\Domain\Enum;

enum NotificationStatusEnum: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
