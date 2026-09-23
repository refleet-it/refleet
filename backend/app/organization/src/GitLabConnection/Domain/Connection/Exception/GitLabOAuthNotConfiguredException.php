<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Domain\Connection\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
#[WithLogLevel(LogLevel::WARNING)]
final class GitLabOAuthNotConfiguredException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'GitLab OAuth is not configured on this server',
            errorCode: 'GITLAB_OAUTH_NOT_CONFIGURED',
        );
    }
}
