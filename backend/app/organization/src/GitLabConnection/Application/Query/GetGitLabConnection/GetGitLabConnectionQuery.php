<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Query\GetGitLabConnection;

final readonly class GetGitLabConnectionQuery
{
    public function __construct(
        public string $organizationId,
    ) {
    }
}
