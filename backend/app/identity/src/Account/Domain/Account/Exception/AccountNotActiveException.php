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
final class AccountNotActiveException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Account is not active',
            errorCode: 'ACCOUNT_NOT_ACTIVE',
            details: [
                'suggestion' => 'Contact the administrator to activate your account',
                'hint' => 'Your account may require email verification or may have been suspended',
            ]
        );
    }
}
