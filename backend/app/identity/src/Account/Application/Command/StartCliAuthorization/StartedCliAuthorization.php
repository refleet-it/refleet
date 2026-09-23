<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\StartCliAuthorization;

/** The device secret is never persisted in the clear; it is handed to the CLI exactly once, here. */
final readonly class StartedCliAuthorization
{
    public function __construct(
        public string $userCode,
        public string $deviceSecret,
        public string $expiresAt,
    ) {
    }
}
