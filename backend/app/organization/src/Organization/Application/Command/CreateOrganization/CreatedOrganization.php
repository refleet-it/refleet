<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\CreateOrganization;

final readonly class CreatedOrganization
{
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
    ) {
    }
}
