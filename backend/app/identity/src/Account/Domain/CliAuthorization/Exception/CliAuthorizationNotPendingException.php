<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\CliAuthorization\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
#[WithLogLevel(LogLevel::INFO)]
final class CliAuthorizationNotPendingException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'This CLI login request has already been decided.',
            errorCode: 'CLI_AUTHORIZATION_ALREADY_DECIDED',
        );
    }
}
