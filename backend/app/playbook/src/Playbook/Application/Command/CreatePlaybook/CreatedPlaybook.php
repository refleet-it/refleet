<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Application\Command\CreatePlaybook;

final readonly class CreatedPlaybook
{
    public function __construct(
        public string $id,
    ) {
    }
}
