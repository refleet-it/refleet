<?php

declare(strict_types=1);

namespace App\Identity\RefreshToken\Infrastructure\Security;

final readonly class GeneratedRefreshToken
{
    public function __construct(
        public string $plainToken,
        public string $hashedToken,
    ) {
    }
}
