<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Command\ConnectGitLab;

final readonly class ConnectedGitLabConnection
{
    public function __construct(
        public string $baseUrl,
        public string $groupPath,
        public string $groupName,
        public string $connectedAt,
    ) {
    }
}
