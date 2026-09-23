<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Query\GetGitLabCredentialsForRunner;

final readonly class GitLabRunnerCredentials
{
    public function __construct(
        public string $baseUrl,
        public string $accessToken,
    ) {
    }
}
