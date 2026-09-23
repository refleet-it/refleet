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
final class InvalidCurrentPasswordException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Current password is incorrect',
            errorCode: 'INVALID_CURRENT_PASSWORD',
            details: [
                'suggestion' => 'Check the correctness of your current password and try again',
            ]
        );
    }
}
