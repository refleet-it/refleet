<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Query\GetGitLabConnection;

use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetGitLabConnectionHandler
{
    public function __construct(
        private GitLabConnectionRepositoryInterface $connections,
    ) {
    }

    public function __invoke(GetGitLabConnectionQuery $query): ?GitLabConnectionOverview
    {
        $connection = $this->connections->findByOrganizationId(OrganizationId::fromString($query->organizationId));

        if (null === $connection) {
            return null;
        }

        return new GitLabConnectionOverview(
            baseUrl: $connection->baseUrl(),
            groupPath: $connection->groupPath(),
            groupName: $connection->groupName(),
            connectedAt: $connection->connectedAt()->format('c'),
            lastSyncedAt: $connection->lastSyncedAt()?->format('c'),
            lastSyncStatus: $connection->lastSyncStatus()->value,
            lastSyncError: $connection->lastSyncError(),
            lastSyncProjectCount: $connection->lastSyncProjectCount(),
            webhookSecret: $connection->webhookSecret(),
            authMethod: $connection->usesOAuth() ? 'oauth' : 'access_token',
        );
    }
}
