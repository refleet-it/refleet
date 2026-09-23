<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\CliAuthorization\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_GONE)]
#[WithLogLevel(LogLevel::INFO)]
final class CliAuthorizationExpiredException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'This CLI login request has expired. Run `refleet login` again.',
            errorCode: 'CLI_AUTHORIZATION_EXPIRED',
        );
    }
}
