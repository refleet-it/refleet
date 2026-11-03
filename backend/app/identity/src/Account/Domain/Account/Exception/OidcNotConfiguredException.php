<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
#[WithLogLevel(LogLevel::WARNING)]
final class OidcNotConfiguredException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Single sign-on is not configured on this server.',
            errorCode: 'OIDC_NOT_CONFIGURED',
            details: [
                'suggestion' => 'Sign in with an email address and password instead',
                'hint' => 'GET /instance lists the providers this instance offers',
            ]
        );
    }
}
