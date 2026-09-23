<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\RevokeApiKey;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class RevokeApiKeyCommand implements CommandInterface
{
    public function __construct(
        public string $accountId,
        public string $apiKeyId,
    ) {
    }
}
