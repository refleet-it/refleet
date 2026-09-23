<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Bus\EmailVerification;

final readonly class EmailVerificationMessage
{
    public function __construct(
        public string $accountId,
        public string $email,
        public string $verificationToken,
    ) {
    }
}
