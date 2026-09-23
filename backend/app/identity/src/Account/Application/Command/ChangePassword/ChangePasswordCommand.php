<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\ChangePassword;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ChangePasswordCommand implements CommandInterface
{
    public function __construct(
        public string $accountId,
        #[\SensitiveParameter]
        public string $currentPassword,
        #[\SensitiveParameter]
        public string $newPassword,
    ) {
    }
}
