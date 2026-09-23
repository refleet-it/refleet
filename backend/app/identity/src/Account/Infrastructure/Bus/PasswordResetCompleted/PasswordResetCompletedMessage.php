<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Bus\PasswordResetCompleted;

final readonly class PasswordResetCompletedMessage
{
    public function __construct(
        public string $accountId,
        public string $email,
    ) {
    }
}
