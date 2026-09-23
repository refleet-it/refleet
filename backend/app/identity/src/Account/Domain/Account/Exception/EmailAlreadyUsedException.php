<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
#[WithLogLevel(LogLevel::INFO)]
final class EmailAlreadyUsedException extends DetailedAppException
{
    public function __construct(string $email)
    {
        parent::__construct(
            message: \sprintf("Email '%s' is already in use in the system", $email),
            errorCode: 'EMAIL_ALREADY_USED',
            details: [
                'email' => $email,
                'suggestion' => 'Use a different email address or log in to your existing account',
            ],
            field: 'email'
        );
    }
}
