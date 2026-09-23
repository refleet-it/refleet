<?php

declare(strict_types=1);

namespace App\Runner\Runner\Domain\Runner\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
#[WithLogLevel(LogLevel::INFO)]
final class RunnerAlreadyArchivedException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'This runner is already archived',
            errorCode: 'RUNNER_ALREADY_ARCHIVED',
        );
    }
}
