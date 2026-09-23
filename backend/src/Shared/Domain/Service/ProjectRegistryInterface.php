<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

/**
 * Maintains the project catalog on behalf of whichever context syncs it from an external
 * source. Synchronous on purpose: the sync is user-triggered and reports back how many
 * projects it registered and archived, so the caller needs the outcome, not a promise.
 */
interface ProjectRegistryInterface
{
    public function register(
        string $organizationId,
        string $externalId,
        string $name,
        string $path,
        ?string $webUrl = null,
        ?string $defaultBranch = null,
        ?string $description = null,
    ): void;

    /**
     * Archives the organization's projects that were absent from the latest sync.
     *
     * @param string[] $seenExternalIds
     *
     * @return int how many projects were archived
     */
    public function archiveMissing(string $organizationId, array $seenExternalIds): int;
}
