<?php

declare(strict_types=1);

namespace App\Organization\Organization\Domain\Organization\Repository;

use App\Organization\Organization\Domain\Organization\Model\Organization;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;

interface OrganizationRepositoryInterface
{
    public function save(Organization $organization): void;

    public function findById(OrganizationId $id): ?Organization;
}
