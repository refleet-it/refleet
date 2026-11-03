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
final class AccountOAuthIdentityConflictException extends DetailedAppException
{
    public function __construct(string $provider)
    {
        parent::__construct(
            message: 'This account is already linked to a different identity at the same provider.',
            errorCode: 'OAUTH_IDENTITY_CONFLICT',
            details: [
                'provider' => $provider,
                'suggestion' => 'Ask an administrator to check which account the provider is linked to',
                'hint' => 'The provider reported a different subject than the one stored for this account, which usually means the instance was pointed at a new issuer',
            ]
        );
    }
}
