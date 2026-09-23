<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Domain\Qualification\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
#[WithLogLevel(LogLevel::INFO)]
final class QualificationArchivedException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'This qualification is archived and can no longer be changed or used',
            errorCode: 'QUALIFICATION_ARCHIVED',
        );
    }
}
