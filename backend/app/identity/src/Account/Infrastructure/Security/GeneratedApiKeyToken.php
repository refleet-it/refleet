<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Security;

final readonly class GeneratedApiKeyToken
{
    public function __construct(
        public string $plainToken,
        public string $prefix,
        public string $hashedSecret,
    ) {
    }
}
