<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\DenyCliAuthorization;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class DenyCliAuthorizationCommand implements CommandInterface
{
    public function __construct(
        public string $userCode,
    ) {
    }
}
