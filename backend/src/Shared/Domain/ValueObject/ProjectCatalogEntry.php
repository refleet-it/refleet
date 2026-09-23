<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * A project as seen by contexts that turn projects into work items. Narrower than the
 * Project context's own read model: only what is needed to identify a project and snapshot
 * it onto a target (see ProjectSnapshot).
 */
final readonly class ProjectCatalogEntry
{
    public function __construct(
        public string $id,
        public string $externalId,
        public string $name,
        public string $path,
        public ?string $defaultBranch,
    ) {
    }
}
