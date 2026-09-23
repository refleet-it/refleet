<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

/**
 * Resolves runner names to runner ids. Targets in other contexts only store the free-text
 * runner name reported at claim time, so linking a displayed name to that runner's page
 * needs this lookup — without depending on the Runner context.
 */
interface RunnerDirectoryInterface
{
    /**
     * @return array<string, string> runner name => runner id
     */
    public function idsByNameForOrganization(string $organizationId): array;
}
