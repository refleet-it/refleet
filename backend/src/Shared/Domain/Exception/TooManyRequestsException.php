<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_TOO_MANY_REQUESTS)]
#[WithLogLevel(LogLevel::INFO)]
final class TooManyRequestsException extends DetailedAppException
{
    public function __construct(int $retryAfterSeconds)
    {
        parent::__construct(
            message: 'Too many requests. Please try again later.',
            errorCode: 'TOO_MANY_REQUESTS',
            details: ['retry_after' => $retryAfterSeconds]
        );
    }
}
