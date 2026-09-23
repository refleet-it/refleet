<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\ClaimCliAuthorization;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ClaimCliAuthorizationCommand implements CommandInterface
{
    public function __construct(
        public string $deviceSecret,
        public string $apiKeyName,
    ) {
    }
}
