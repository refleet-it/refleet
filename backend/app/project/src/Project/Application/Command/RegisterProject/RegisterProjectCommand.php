<?php

declare(strict_types=1);

namespace App\Project\Project\Application\Command\RegisterProject;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class RegisterProjectCommand implements CommandInterface
{
    public function __construct(
        public string $organizationId,
        public string $externalId,
        public string $name,
        public string $path,
        public ?string $webUrl = null,
        public ?string $defaultBranch = null,
        public ?string $description = null,
    ) {
    }
}
