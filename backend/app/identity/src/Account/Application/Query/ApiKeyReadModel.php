<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Query;

final readonly class ApiKeyReadModel
{
    public function __construct(
        public string $id,
        public string $name,
        public string $prefix,
        public string $createdAt,
        public ?string $lastUsedAt,
        public ?string $revokedAt,
    ) {
    }
}
