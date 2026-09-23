<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\ArchiveRunner;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ArchiveRunnerCommand implements CommandInterface
{
    public function __construct(
        public string $runnerId,
        public string $organizationId,
    ) {
    }
}
