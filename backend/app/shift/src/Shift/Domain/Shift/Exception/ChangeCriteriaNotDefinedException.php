<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\Shift\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
#[WithLogLevel(LogLevel::INFO)]
final class ChangeCriteriaNotDefinedException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Change criteria must be defined before starting the change',
            errorCode: 'CHANGE_CRITERIA_NOT_DEFINED',
        );
    }
}
