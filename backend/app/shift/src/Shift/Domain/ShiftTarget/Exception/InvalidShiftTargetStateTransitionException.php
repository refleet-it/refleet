<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\ShiftTarget\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
#[WithLogLevel(LogLevel::INFO)]
final class InvalidShiftTargetStateTransitionException extends DetailedAppException
{
    public function __construct(ShiftTargetStatusEnum $currentStatus, string $attemptedAction)
    {
        parent::__construct(
            message: \sprintf('Cannot %s a shift target in status "%s".', $attemptedAction, $currentStatus->value),
            errorCode: 'INVALID_SHIFT_TARGET_STATE_TRANSITION',
            details: [
                'currentStatus' => $currentStatus->value,
                'attemptedAction' => $attemptedAction,
            ],
        );
    }
}
