<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_FORBIDDEN)]
#[WithLogLevel(LogLevel::INFO)]
final class SelfRegistrationNotAllowedException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'This instance does not accept self-registration. Ask an administrator for an invitation.',
            errorCode: 'SELF_REGISTRATION_NOT_ALLOWED',
            details: [
                'suggestion' => 'Ask an administrator to invite you, then sign in again with the same provider',
                'hint' => 'Signing in with an external provider does not create an account on this instance',
            ]
        );
    }
}
