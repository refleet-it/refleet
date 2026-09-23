<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Command\SyncGitLabProjects;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class SyncGitLabProjectsCommand implements CommandInterface
{
    public function __construct(
        public string $organizationId,
    ) {
    }
}
