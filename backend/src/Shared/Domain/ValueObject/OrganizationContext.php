<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * The calling account's organization membership, as seen from outside the Organization
 * context. Lets other contexts resolve "which organization is this request for" without
 * depending on Organization's own query and read model.
 */
final readonly class OrganizationContext
{
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
    ) {
    }
}
