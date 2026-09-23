<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Command\ConnectGitLab;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ConnectGitLabCommand implements CommandInterface
{
    public function __construct(
        public string $organizationId,
        public string $accountId,
        public string $groupPath,
        public string $accessToken,
        public ?string $baseUrl = null,
    ) {
    }
}
