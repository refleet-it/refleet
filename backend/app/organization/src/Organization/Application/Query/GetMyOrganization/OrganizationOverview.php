<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Query\GetMyOrganization;

final readonly class OrganizationOverview
{
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
    ) {
    }
}
