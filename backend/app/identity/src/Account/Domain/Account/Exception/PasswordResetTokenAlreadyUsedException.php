<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Exception;

use App\Shared\Domain\Exception\AppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_UNPROCESSABLE_ENTITY)]
#[WithLogLevel(LogLevel::INFO)]
final class PasswordResetTokenAlreadyUsedException extends AppException
{
    public function __construct()
    {
        parent::__construct('Password reset token has already been used.');
    }
}
