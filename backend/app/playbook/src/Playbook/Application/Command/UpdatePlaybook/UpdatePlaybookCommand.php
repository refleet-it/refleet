<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Application\Command\UpdatePlaybook;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class UpdatePlaybookCommand implements CommandInterface
{
    /**
     * @param list<array{name: string, label?: string|null, default?: string|null, required?: bool}> $parameters
     */
    public function __construct(
        public string $playbookId,
        public string $organizationId,
        public string $name,
        public ?string $description,
        public string $kind,
        public string $appliesTo,
        public string $body,
        public bool $default = false,
        public array $parameters = [],
        public ?string $engine = null,
        public ?string $model = null,
    ) {
    }
}
