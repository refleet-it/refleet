<?php

declare(strict_types=1);

namespace App\Identity\RefreshToken\Domain\RefreshToken\Exception;

use App\Shared\Domain\Exception\AppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_UNAUTHORIZED)]
#[WithLogLevel(LogLevel::INFO)]
final class RefreshTokenExpiredException extends AppException
{
    public function __construct()
    {
        parent::__construct('Refresh token expired');
    }
}
