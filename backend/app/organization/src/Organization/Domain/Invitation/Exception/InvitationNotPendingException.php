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
final class InvitationNotPendingException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'This invitation is no longer pending',
            errorCode: 'INVITATION_NOT_PENDING',
        );
    }
}
