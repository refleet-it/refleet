<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Query\GetGitLabConnection;

final readonly class GitLabConnectionOverview
{
    public function __construct(
        public string $baseUrl,
        public string $groupPath,
        public string $groupName,
        public string $connectedAt,
        public ?string $lastSyncedAt,
        public string $lastSyncStatus,
        public ?string $lastSyncError,
        public ?int $lastSyncProjectCount,
        public string $authMethod,
    ) {
    }
}
