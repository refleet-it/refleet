<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Query\GetGitLabCredentialsForRunner;

use App\Organization\GitLabConnection\Domain\Connection\Exception\GitLabConnectionNotFoundException;
use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabAccessTokenResolver;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetGitLabCredentialsForRunnerHandler
{
    public function __construct(
        private GitLabConnectionRepositoryInterface $connections,
        private GitLabAccessTokenResolver $accessTokens,
    ) {
    }

    public function __invoke(GetGitLabCredentialsForRunnerQuery $query): GitLabRunnerCredentials
    {
        $connection = $this->connections->findByOrganizationId(OrganizationId::fromString($query->organizationId));

        if (null === $connection) {
            throw new GitLabConnectionNotFoundException();
        }

        return new GitLabRunnerCredentials(
            baseUrl: $connection->baseUrl(),
            accessToken: $this->accessTokens->resolve($connection),
        );
    }
}
