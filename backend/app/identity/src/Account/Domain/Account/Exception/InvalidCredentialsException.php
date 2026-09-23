<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_UNPROCESSABLE_ENTITY)]
#[WithLogLevel(LogLevel::INFO)]
final class InvalidCredentialsException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Invalid email or password',
            errorCode: 'INVALID_CREDENTIALS',
            details: [
                'suggestion' => 'Check the correctness of the entered data and try again',
                'hint' => 'Make sure you are using the correct email address and password',
            ]
        );
    }
}
