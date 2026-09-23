<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(403)]
#[WithLogLevel(LogLevel::INFO)]
final class EmailNotVerifiedException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            'Email address has not been verified. Please check your email for verification link.',
            'EMAIL_NOT_VERIFIED'
        );
    }
}
