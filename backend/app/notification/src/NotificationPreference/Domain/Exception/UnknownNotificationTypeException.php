<?php

declare(strict_types=1);

namespace App\Notification\NotificationPreference\Domain\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_NOT_FOUND)]
#[WithLogLevel(LogLevel::INFO)]
final class UnknownNotificationTypeException extends DetailedAppException
{
    public function __construct(string $notificationType)
    {
        parent::__construct(
            message: \sprintf('Unknown notification type: %s', $notificationType),
            errorCode: 'UNKNOWN_NOTIFICATION_TYPE',
        );
    }
}
