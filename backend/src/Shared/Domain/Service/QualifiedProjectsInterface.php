<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

use App\Shared\Domain\ValueObject\ProjectCatalogEntry;

/**
 * Read access to the projects a qualification has already vetted, for contexts that turn
 * qualification results into follow-up work without depending on the Qualification context.
 */
interface QualifiedProjectsInterface
{
    /**
     * @param string[]|null $projectIds when null, only targets that came out qualified;
     *                                  otherwise exactly these projects regardless of outcome
     *
     * @return ProjectCatalogEntry[]
     */
    public function forQualification(string $organizationId, string $qualificationId, ?array $projectIds = null): array;
}
