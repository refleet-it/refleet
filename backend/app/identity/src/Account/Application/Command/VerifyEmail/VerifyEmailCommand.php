<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\VerifyEmail;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class VerifyEmailCommand implements CommandInterface
{
    public function __construct(
        public string $token,
    ) {
    }
}
