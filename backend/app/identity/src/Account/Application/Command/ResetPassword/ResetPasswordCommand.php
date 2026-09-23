<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\ResetPassword;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ResetPasswordCommand implements CommandInterface
{
    public function __construct(
        public string $token,
        public string $newPassword,
    ) {
    }
}
