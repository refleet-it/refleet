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
final class EmailVerificationTokenExpiredException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            'Email verification token has expired.',
            'EMAIL_VERIFICATION_TOKEN_EXPIRED'
        );
    }
}
