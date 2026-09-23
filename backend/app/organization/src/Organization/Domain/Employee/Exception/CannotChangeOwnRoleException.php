<?php

declare(strict_types=1);

namespace App\Organization\Organization\Domain\Employee\Exception;

use App\Shared\Domain\Exception\BusinessAccessDeniedException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_FORBIDDEN)]
#[WithLogLevel(LogLevel::INFO)]
final class CannotChangeOwnRoleException extends BusinessAccessDeniedException
{
    public function __construct()
    {
        parent::__construct('The organization owner cannot change their own role');
    }
}
