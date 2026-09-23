<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\CreateApiKey;

/**
 * The only place the plaintext token ever exists outside the client's hands -
 * it is never persisted, and the caller must show it to the user exactly once.
 */
final readonly class CreatedApiKey
{
    public function __construct(
        public string $id,
        public string $name,
        public string $prefix,
        public string $token,
        public string $createdAt,
    ) {
    }
}
