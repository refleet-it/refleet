<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\Shift\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_UNPROCESSABLE_ENTITY)]
#[WithLogLevel(LogLevel::INFO)]
final class ExplicitProjectSelectionRequiredException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Creating a shift without a qualification requires an explicit, non-empty list of target project ids',
            errorCode: 'EXPLICIT_PROJECT_SELECTION_REQUIRED',
        );
    }
}
