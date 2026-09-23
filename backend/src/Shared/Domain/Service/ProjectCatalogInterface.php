<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

use App\Shared\Domain\ValueObject\ProjectCatalogEntry;

/**
 * Read access to an organization's projects for contexts that build work items out of
 * them (qualifications, shifts) without depending on the Project context.
 */
interface ProjectCatalogInterface
{
    /**
     * @return ProjectCatalogEntry[]
     */
    public function allForOrganization(string $organizationId): array;

    /**
     * @param string[] $projectIds
     *
     * @return ProjectCatalogEntry[]
     */
    public function byIds(string $organizationId, array $projectIds): array;
}
