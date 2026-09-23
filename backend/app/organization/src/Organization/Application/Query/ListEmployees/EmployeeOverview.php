<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Query\ListEmployees;

final readonly class EmployeeOverview
{
    public function __construct(
        public string $accountId,
        public string $email,
        public string $role,
        public ?string $joinedAt,
    ) {
    }
}
