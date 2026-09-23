<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\ChangeEmployeeRole;

final readonly class TransferredOwnership
{
    public function __construct(
        public string $newOwnerAccountId,
        public string $newOwnerEmail,
    ) {
    }
}
