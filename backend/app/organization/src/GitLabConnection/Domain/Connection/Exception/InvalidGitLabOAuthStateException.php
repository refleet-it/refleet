<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Domain\Connection\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_BAD_REQUEST)]
#[WithLogLevel(LogLevel::INFO)]
final class InvalidGitLabOAuthStateException extends DetailedAppException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            message: 'GitLab authorization could not be completed: '.$reason,
            errorCode: 'INVALID_GITLAB_OAUTH_STATE',
        );
    }
}
