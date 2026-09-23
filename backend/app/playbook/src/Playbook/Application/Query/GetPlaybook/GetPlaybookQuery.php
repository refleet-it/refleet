<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Application\Query\GetPlaybook;

final readonly class GetPlaybookQuery
{
    public function __construct(
        public string $playbookId,
        public string $organizationId,
    ) {
    }
}
