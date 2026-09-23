<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_UNPROCESSABLE_ENTITY)]
#[WithLogLevel(LogLevel::INFO)]
final class ImpersonationNotAllowedException extends DetailedAppException
{
    private const string ERROR_CODE = 'IMPERSONATION_NOT_ALLOWED';

    public function __construct(string $message)
    {
        parent::__construct(
            message: $message,
            errorCode: self::ERROR_CODE,
            details: [
                'suggestion' => 'Check the permissions to impersonate this user',
                'hint' => 'Only administrators can impersonate users who are not administrators',
            ]
        );
    }
}
