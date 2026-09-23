<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command;

final readonly class TokensDto
{
    public function __construct(
        public string $jwtToken,
        public ?string $refreshToken,
    ) {
    }
}
