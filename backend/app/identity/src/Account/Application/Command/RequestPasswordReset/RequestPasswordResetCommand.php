<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\RequestPasswordReset;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class RequestPasswordResetCommand implements CommandInterface
{
    public function __construct(
        public string $email,
    ) {
    }
}
