<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\StartCliAuthorization;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class StartCliAuthorizationCommand implements CommandInterface
{
    public function __construct(
        public string $runnerName,
    ) {
    }
}
