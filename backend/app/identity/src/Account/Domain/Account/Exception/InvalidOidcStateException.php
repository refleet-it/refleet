<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_BAD_REQUEST)]
#[WithLogLevel(LogLevel::INFO)]
final class InvalidOidcStateException extends DetailedAppException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            message: 'Sign-in could not be completed: '.$reason,
            errorCode: 'INVALID_OIDC_STATE',
            details: [
                'suggestion' => 'Start signing in again from this instance',
            ]
        );
    }
}
