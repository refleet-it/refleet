<?php

declare(strict_types=1);

namespace App\Organization\Organization\Domain\Employee\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
#[WithLogLevel(LogLevel::INFO)]
final class AccountAlreadyBelongsToOrganizationException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'This account already belongs to an organization',
            errorCode: 'ACCOUNT_ALREADY_HAS_ORGANIZATION',
        );
    }
}
