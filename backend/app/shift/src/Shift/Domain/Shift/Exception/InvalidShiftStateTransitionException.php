<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\Shift\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use App\Shift\Shift\Domain\Shift\Enum\ShiftStatusEnum;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
#[WithLogLevel(LogLevel::INFO)]
final class InvalidShiftStateTransitionException extends DetailedAppException
{
    public function __construct(ShiftStatusEnum $currentStatus, string $attemptedAction)
    {
        parent::__construct(
            message: \sprintf('Cannot %s a shift in status "%s".', $attemptedAction, $currentStatus->value),
            errorCode: 'INVALID_SHIFT_STATE_TRANSITION',
            details: [
                'currentStatus' => $currentStatus->value,
                'attemptedAction' => $attemptedAction,
            ],
        );
    }
}
