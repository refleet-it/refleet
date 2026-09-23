<?php

declare(strict_types=1);

namespace App\Identity\RefreshToken\Application\Command\Refresh;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class RefreshCommand implements CommandInterface
{
    public function __construct(
        public string $refreshToken,
    ) {
    }
}
