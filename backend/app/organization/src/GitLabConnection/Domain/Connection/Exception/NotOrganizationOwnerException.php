<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Domain\Connection\Exception;

use App\Shared\Domain\Exception\BusinessAccessDeniedException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_FORBIDDEN)]
#[WithLogLevel(LogLevel::INFO)]
final class NotOrganizationOwnerException extends BusinessAccessDeniedException
{
    public function __construct()
    {
        parent::__construct('Only the organization owner can manage the GitLab connection');
    }
}
