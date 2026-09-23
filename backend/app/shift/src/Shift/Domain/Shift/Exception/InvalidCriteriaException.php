<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\Shift\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_UNPROCESSABLE_ENTITY)]
#[WithLogLevel(LogLevel::INFO)]
final class InvalidCriteriaException extends DetailedAppException
{
    public function __construct(string $message)
    {
        parent::__construct(
            message: $message,
            errorCode: 'INVALID_CRITERIA',
        );
    }
}
