<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\RequestRunnerUpdate;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class RequestRunnerUpdateCommand implements CommandInterface
{
    public function __construct(
        public string $runnerId,
        public string $organizationId,
    ) {
    }
}
