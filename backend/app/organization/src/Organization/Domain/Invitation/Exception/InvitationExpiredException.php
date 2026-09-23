<?php

declare(strict_types=1);

namespace App\Organization\Organization\Domain\Invitation\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_BAD_REQUEST)]
#[WithLogLevel(LogLevel::INFO)]
final class InvitationExpiredException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'This invitation has expired',
            errorCode: 'INVITATION_EXPIRED',
        );
    }
}
