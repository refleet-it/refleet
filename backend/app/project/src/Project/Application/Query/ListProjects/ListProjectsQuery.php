<?php

declare(strict_types=1);

namespace App\Project\Project\Application\Query\ListProjects;

final readonly class ListProjectsQuery
{
    public function __construct(
        public string $organizationId,
    ) {
    }
}
