<?php

declare(strict_types=1);

namespace App\Notification\Notification\Infrastructure\Bus\PasswordResetCompleted;

final readonly class PasswordResetCompletedMessage
{
    public function __construct(
        public string $accountId,
        public string $email,
    ) {
    }
}
