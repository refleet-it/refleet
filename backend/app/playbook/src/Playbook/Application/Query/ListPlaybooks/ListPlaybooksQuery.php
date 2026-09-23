<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Application\Query\ListPlaybooks;

final readonly class ListPlaybooksQuery
{
    public function __construct(
        public string $organizationId,
        public ?string $appliesTo = null,
    ) {
    }
}
