<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Bus\PasswordReset;

final readonly class PasswordResetMessage
{
    public function __construct(
        public string $accountId,
        public string $email,
        public string $resetToken,
    ) {
    }
}
