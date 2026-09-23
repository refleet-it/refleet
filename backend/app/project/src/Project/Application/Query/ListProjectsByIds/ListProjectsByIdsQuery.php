<?php

declare(strict_types=1);

namespace App\Project\Project\Application\Query\ListProjectsByIds;

final readonly class ListProjectsByIdsQuery
{
    /**
     * @param string[] $ids
     */
    public function __construct(
        public string $organizationId,
        public array $ids,
    ) {
    }
}
