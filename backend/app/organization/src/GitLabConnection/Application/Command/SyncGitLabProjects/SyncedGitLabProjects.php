<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Command\SyncGitLabProjects;

final readonly class SyncedGitLabProjects
{
    public function __construct(
        public int $syncedCount,
        public int $failedCount,
        public int $archivedCount,
        public string $lastSyncedAt,
    ) {
    }
}
