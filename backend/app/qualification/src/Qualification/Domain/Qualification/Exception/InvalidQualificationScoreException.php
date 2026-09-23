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
final class InvalidQualificationScoreException extends DetailedAppException
{
    public function __construct(int $value)
    {
        parent::__construct(
            message: \sprintf('Qualification score must be between 1 and 5, got %d.', $value),
            errorCode: 'INVALID_QUALIFICATION_SCORE',
            details: ['score' => $value],
        );
    }
}
