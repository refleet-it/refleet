<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\ChangeEmployeeRole;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ChangeEmployeeRoleCommand implements CommandInterface
{
    public function __construct(
        public string $requestingAccountId,
        public string $targetAccountId,
    ) {
    }
}
