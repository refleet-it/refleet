<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Command\ConnectGitLabViaOAuth;

use App\Organization\GitLabConnection\Application\Command\ConnectGitLab\ConnectedGitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\Exception\GitLabOAuthNotConfiguredException;
use App\Organization\GitLabConnection\Domain\Connection\Model\GitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabApiClientInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabOAuthClientInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabTokenEncryptorInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\AccountId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabConnectionId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabOAuthTokens;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ConnectGitLabViaOAuthHandler
{
    public function __construct(
        private GitLabConnectionRepositoryInterface $connections,
        private GitLabApiClientInterface $gitLabApiClient,
        private GitLabOAuthClientInterface $oauthClient,
        private GitLabTokenEncryptorInterface $tokenEncryptor,
    ) {
    }

    public function __invoke(ConnectGitLabViaOAuthCommand $command): ConnectedGitLabConnection
    {
        if (!$this->oauthClient->isConfigured()) {
            throw new GitLabOAuthNotConfiguredException();
        }

        $organizationId = OrganizationId::fromString($command->organizationId);
        $accountId = AccountId::fromString($command->accountId);
        $baseUrl = $this->oauthClient->baseUrl();

        $tokens = $this->oauthClient->exchangeCode($command->code);
        $group = $this->gitLabApiClient->resolveGroup($baseUrl, $tokens->accessToken, $command->groupPath);

        $connection = $this->store($organizationId, $accountId, $baseUrl, $group, $tokens);

        return new ConnectedGitLabConnection(
            baseUrl: $connection->baseUrl(),
            groupPath: $connection->groupPath(),
            groupName: $connection->groupName(),
            connectedAt: $connection->connectedAt()->format('c'),
        );
    }

    /**
     * @param array{id: string, name: string, fullPath: string} $group
     */
    private function store(OrganizationId $organizationId, AccountId $accountId, string $baseUrl, array $group, GitLabOAuthTokens $tokens): GitLabConnection
    {
        $accessTokenCiphertext = $this->tokenEncryptor->encrypt($tokens->accessToken);
        $refreshTokenCiphertext = $this->tokenEncryptor->encrypt($tokens->refreshToken);

        $connection = $this->connections->findByOrganizationId($organizationId);

        if (null === $connection) {
            $connection = GitLabConnection::connect(
                id: GitLabConnectionId::generate(),
                organizationId: $organizationId,
                baseUrl: $baseUrl,
                groupId: $group['id'],
                groupPath: $group['fullPath'],
                groupName: $group['name'],
                accessTokenCiphertext: $accessTokenCiphertext,
                connectedByAccountId: $accountId,
                refreshTokenCiphertext: $refreshTokenCiphertext,
                accessTokenExpiresAt: $tokens->expiresAt,
            );
        } else {
            $connection->reconnect(
                baseUrl: $baseUrl,
                groupId: $group['id'],
                groupPath: $group['fullPath'],
                groupName: $group['name'],
                accessTokenCiphertext: $accessTokenCiphertext,
                connectedByAccountId: $accountId,
                refreshTokenCiphertext: $refreshTokenCiphertext,
                accessTokenExpiresAt: $tokens->expiresAt,
            );
        }

        $this->connections->save($connection);

        return $connection;
    }
}
