<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Query\GetCliAuthorization;

final readonly class CliAuthorizationReadModel
{
    public function __construct(
        public string $runnerName,
        public string $status,
        public string $createdAt,
        public string $expiresAt,
    ) {
    }
}
