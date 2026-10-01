<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Service;

use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabAccessTokenResolver;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabApiClientInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use App\Shared\Domain\Service\MergeRequestStateReaderInterface;
use Psr\Log\LoggerInterface;

/**
 * Reads merge request states with the organization's own GitLab credentials. Every
 * failure is swallowed into a missing entry and a log line, per the port's contract: the
 * caller is a poller walking the whole fleet, and a revoked token or an unreachable
 * self-managed instance must cost that one organization its round, not everyone else's.
 */
final readonly class GitLabMergeRequestStateReader implements MergeRequestStateReaderInterface
{
    public function __construct(
        private GitLabConnectionRepositoryInterface $connections,
        private GitLabApiClientInterface $gitLabApiClient,
        private GitLabAccessTokenResolver $accessTokens,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function statesFor(string $organizationId, array $iidsByProjectExternalId): array
    {
        if ([] === $iidsByProjectExternalId) {
            return [];
        }

        $connection = $this->connections->findByOrganizationId(OrganizationId::fromString($organizationId));

        if (null === $connection) {
            return [];
        }

        try {
            $accessToken = $this->accessTokens->resolve($connection);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Could not obtain a GitLab access token to read merge request states', [
                'organizationId' => $organizationId,
                'error' => $throwable->getMessage(),
            ]);

            return [];
        }

        $states = [];

        foreach ($iidsByProjectExternalId as $projectExternalId => $iids) {
            $projectStates = $this->statesForProject($connection->baseUrl(), $accessToken, (string) $projectExternalId, $iids, $organizationId);

            if ([] !== $projectStates) {
                $states[(string) $projectExternalId] = $projectStates;
            }
        }

        return $states;
    }

    /**
     * @param list<string> $iids
     *
     * @return array<string, string>
     */
    private function statesForProject(string $baseUrl, string $accessToken, string $projectExternalId, array $iids, string $organizationId): array
    {
        try {
            return $this->gitLabApiClient->listMergeRequestStates($baseUrl, $accessToken, $projectExternalId, $iids);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Could not read merge request states from GitLab', [
                'organizationId' => $organizationId,
                'projectExternalId' => $projectExternalId,
                'error' => $throwable->getMessage(),
            ]);

            return [];
        }
    }
}
