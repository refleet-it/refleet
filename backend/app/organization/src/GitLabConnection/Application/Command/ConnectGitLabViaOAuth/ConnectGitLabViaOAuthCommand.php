<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Command\ConnectGitLabViaOAuth;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ConnectGitLabViaOAuthCommand implements CommandInterface
{
    public function __construct(
        public string $organizationId,
        public string $accountId,
        public string $code,
        public string $groupPath,
    ) {
    }
}
