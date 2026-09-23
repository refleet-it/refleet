<?php

declare(strict_types=1);

namespace App\Runner\Runner\Domain\RunnerJob\Exception;

use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobStatusEnum;
use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
#[WithLogLevel(LogLevel::INFO)]
final class InvalidRunnerJobStateTransitionException extends DetailedAppException
{
    public function __construct(RunnerJobStatusEnum $currentStatus, string $attemptedAction)
    {
        parent::__construct(
            message: \sprintf('Cannot %s a runner job in status "%s".', $attemptedAction, $currentStatus->value),
            errorCode: 'INVALID_RUNNER_JOB_STATE_TRANSITION',
            details: [
                'currentStatus' => $currentStatus->value,
                'attemptedAction' => $attemptedAction,
            ],
        );
    }
}
