<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_BAD_REQUEST)]
#[WithLogLevel(LogLevel::INFO)]
final class InvalidPaginationParametersException extends DetailedAppException
{
    public function __construct(string $message)
    {
        parent::__construct(
            message: $message,
            errorCode: 'INVALID_PAGINATION_PARAMETERS',
            details: [],
        );
    }
}
