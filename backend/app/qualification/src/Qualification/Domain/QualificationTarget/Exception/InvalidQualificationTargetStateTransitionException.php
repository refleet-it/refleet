<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Domain\QualificationTarget\Exception;

use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
#[WithLogLevel(LogLevel::INFO)]
final class InvalidQualificationTargetStateTransitionException extends DetailedAppException
{
    public function __construct(QualificationTargetStatusEnum $currentStatus, string $attemptedAction)
    {
        parent::__construct(
            message: \sprintf('Cannot %s a qualification target in status "%s".', $attemptedAction, $currentStatus->value),
            errorCode: 'INVALID_QUALIFICATION_TARGET_STATE_TRANSITION',
            details: [
                'currentStatus' => $currentStatus->value,
                'attemptedAction' => $attemptedAction,
            ],
        );
    }
}
