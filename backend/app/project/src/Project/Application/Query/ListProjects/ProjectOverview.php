<?php

declare(strict_types=1);

namespace App\Project\Project\Application\Query\ListProjects;

final readonly class ProjectOverview
{
    public function __construct(
        public string $id,
        public string $name,
        public string $externalId,
        public string $path,
        public ?string $webUrl,
        public ?string $defaultBranch,
        public ?string $description,
        public string $createdAt,
        public ?string $lastSyncedAt,
    ) {
    }
}
