<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\AddEmployee;

final readonly class AddedEmployee
{
    public function __construct(
        public string $accountId,
        public string $email,
        public string $role,
    ) {
    }
}
