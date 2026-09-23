<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Command\ConnectGitLab;

use App\Organization\GitLabConnection\Domain\Connection\Model\GitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabApiClientInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabTokenEncryptorInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\AccountId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabConnectionId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ConnectGitLabHandler
{
    private const string DEFAULT_BASE_URL = 'https://gitlab.com';

    public function __construct(
        private GitLabConnectionRepositoryInterface $connections,
        private GitLabApiClientInterface $gitLabApiClient,
        private GitLabTokenEncryptorInterface $tokenEncryptor,
    ) {
    }

    public function __invoke(ConnectGitLabCommand $command): ConnectedGitLabConnection
    {
        $organizationId = OrganizationId::fromString($command->organizationId);
        $accountId = AccountId::fromString($command->accountId);
        $baseUrl = $this->normalizeBaseUrl($command->baseUrl);

        $group = $this->gitLabApiClient->resolveGroup($baseUrl, $command->accessToken, $command->groupPath);
        $ciphertext = $this->tokenEncryptor->encrypt($command->accessToken);

        $connection = $this->connections->findByOrganizationId($organizationId);

        if (null === $connection) {
            $connection = GitLabConnection::connect(
                id: GitLabConnectionId::generate(),
                organizationId: $organizationId,
                baseUrl: $baseUrl,
                groupId: $group['id'],
                groupPath: $group['fullPath'],
                groupName: $group['name'],
                accessTokenCiphertext: $ciphertext,
                connectedByAccountId: $accountId,
            );
        } else {
            $connection->reconnect(
                baseUrl: $baseUrl,
                groupId: $group['id'],
                groupPath: $group['fullPath'],
                groupName: $group['name'],
                accessTokenCiphertext: $ciphertext,
                connectedByAccountId: $accountId,
            );
        }

        $this->connections->save($connection);

        return new ConnectedGitLabConnection(
            baseUrl: $connection->baseUrl(),
            groupPath: $connection->groupPath(),
            groupName: $connection->groupName(),
            connectedAt: $connection->connectedAt()->format('c'),
        );
    }

    private function normalizeBaseUrl(?string $baseUrl): string
    {
        if (null === $baseUrl || '' === \trim($baseUrl)) {
            return self::DEFAULT_BASE_URL;
        }

        return \rtrim(\trim($baseUrl), '/');
    }
}
