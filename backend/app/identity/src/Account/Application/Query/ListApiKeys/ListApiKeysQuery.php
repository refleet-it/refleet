<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Query\ListApiKeys;

final readonly class ListApiKeysQuery
{
    public function __construct(
        public string $accountId,
    ) {
    }
}
