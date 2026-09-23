<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Security;

final readonly class GeneratedCliAuthorizationCodes
{
    public function __construct(
        public string $userCode,
        public string $deviceSecret,
        public string $deviceSecretHash,
    ) {
    }
}
