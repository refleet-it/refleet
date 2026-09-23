<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Query\GetGitLabCredentialsForRunner;

final readonly class GetGitLabCredentialsForRunnerQuery
{
    public function __construct(
        public string $organizationId,
    ) {
    }
}
