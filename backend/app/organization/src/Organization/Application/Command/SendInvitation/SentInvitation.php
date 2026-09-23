<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\SendInvitation;

final readonly class SentInvitation
{
    public function __construct(
        public string $id,
        public string $email,
        public string $expiresAt,
    ) {
    }
}
