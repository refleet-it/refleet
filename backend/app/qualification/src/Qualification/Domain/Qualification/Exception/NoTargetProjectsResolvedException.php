<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Domain\Qualification\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_UNPROCESSABLE_ENTITY)]
#[WithLogLevel(LogLevel::INFO)]
final class NoTargetProjectsResolvedException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'No target projects could be resolved for this qualification',
            errorCode: 'NO_TARGET_PROJECTS_RESOLVED',
        );
    }
}
