<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Command\DisconnectGitLab;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class DisconnectGitLabCommand implements CommandInterface
{
    public function __construct(
        public string $organizationId,
    ) {
    }
}
