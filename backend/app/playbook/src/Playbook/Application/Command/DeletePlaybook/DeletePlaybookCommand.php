<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Application\Command\DeletePlaybook;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class DeletePlaybookCommand implements CommandInterface
{
    public function __construct(
        public string $playbookId,
        public string $organizationId,
    ) {
    }
}
