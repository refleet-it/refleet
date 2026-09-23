<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_NOT_FOUND)]
#[WithLogLevel(LogLevel::INFO)]
final class EmailVerificationTokenNotFoundException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            'Email verification token not found.',
            'EMAIL_VERIFICATION_TOKEN_NOT_FOUND'
        );
    }
}
