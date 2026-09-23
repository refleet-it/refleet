<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\CreateApiKey;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class CreateApiKeyCommand implements CommandInterface
{
    public function __construct(
        public string $accountId,
        public string $name,
    ) {
    }
}
