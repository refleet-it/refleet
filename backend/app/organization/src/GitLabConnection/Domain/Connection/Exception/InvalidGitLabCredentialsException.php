<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Domain\Connection\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_UNPROCESSABLE_ENTITY)]
#[WithLogLevel(LogLevel::INFO)]
final class InvalidGitLabCredentialsException extends DetailedAppException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            message: 'Could not connect to GitLab: '.$reason,
            errorCode: 'INVALID_GITLAB_CREDENTIALS',
        );
    }
}
