<?php

declare(strict_types=1);

namespace App\Project\Project\Application\Command\ArchiveMissingProjects;

final readonly class ArchivedMissingProjects
{
    public function __construct(
        public int $archivedCount,
    ) {
    }
}
