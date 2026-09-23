<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
#[WithLogLevel(LogLevel::INFO)]
final class AccountHasNoOrganizationException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'This account does not belong to an organization yet',
            errorCode: 'ACCOUNT_HAS_NO_ORGANIZATION',
        );
    }
}
