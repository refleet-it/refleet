<?php

declare(strict_types=1);

namespace App\Project\Project\Application\Command\ArchiveMissingProjects;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ArchiveMissingProjectsCommand implements CommandInterface
{
    /**
     * @param string[] $seenExternalIds
     */
    public function __construct(
        public string $organizationId,
        public array $seenExternalIds,
    ) {
    }
}
