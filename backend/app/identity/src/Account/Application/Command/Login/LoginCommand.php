<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\Login;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class LoginCommand implements CommandInterface
{
    public function __construct(
        public string $email,
        #[\SensitiveParameter]
        public string $plainPassword,
    ) {
    }
}
