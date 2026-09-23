<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Domain\Playbook\Repository;

use App\Playbook\Playbook\Domain\Playbook\Model\Playbook;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\OrganizationId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookId;

interface PlaybookRepositoryInterface
{
    public function save(Playbook $playbook): void;

    public function remove(Playbook $playbook): void;

    /**
     * Scoped at the query level so a playbook belonging to another organization comes
     * back as null instead of having to be filtered out by the caller.
     */
    public function findByIdForOrganization(PlaybookId $id, OrganizationId $organizationId): ?Playbook;

    /**
     * @return Playbook[] ordered by name
     */
    public function findByOrganizationId(OrganizationId $organizationId): array;
}
