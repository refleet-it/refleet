<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\GitLabConnection\Domain\Connection\Service;

use App\Organization\GitLabConnection\Domain\Connection\Model\GitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabAccessTokenResolver;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabOAuthClientInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabTokenEncryptorInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\AccountId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabConnectionId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabOAuthTokens;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GitLabAccessTokenResolver::class)]
final class GitLabAccessTokenResolverTest extends TestCase
{
    private GitLabConnectionRepositoryInterface&MockObject $connections;

    private GitLabOAuthClientInterface&MockObject $oauthClient;

    private GitLabAccessTokenResolver $resolver;

    #[Test]
    public function decrypts_a_pasted_token_without_touching_gitlab(): void
    {
        // Arrange
        $connection = $this->connection(refreshTokenCiphertext: null, expiresAt: null);
        $this->oauthClient->expects($this->never())->method('refresh');
        $this->connections->expects($this->never())->method('withExclusiveLock');

        // Act
        $token = $this->resolver->resolve($connection);

        // Assert
        Assert::assertSame('plain:access', $token);
    }

    #[Test]
    public function returns_an_oauth_access_token_that_is_still_fresh_as_is(): void
    {
        // Arrange
        $connection = $this->connection(refreshTokenCiphertext: 'enc:refresh', expiresAt: new \DateTimeImmutable('+1 hour'));
        $this->oauthClient->expects($this->never())->method('refresh');
        $this->connections->expects($this->never())->method('withExclusiveLock');

        // Act
        $token = $this->resolver->resolve($connection);

        // Assert
        Assert::assertSame('plain:access', $token);
    }

    #[Test]
    public function refreshes_an_expiring_oauth_token_under_the_row_lock_and_stores_the_new_pair(): void
    {
        // Arrange
        $connection = $this->connection(refreshTokenCiphertext: 'enc:refresh', expiresAt: new \DateTimeImmutable('+2 minutes'));
        $expiresAt = new \DateTimeImmutable('+2 hours');

        $this->connections->expects($this->once())->method('withExclusiveLock')
            ->willReturnCallback(static fn (OrganizationId $id, \Closure $work): mixed => $work($connection));
        $this->oauthClient->expects($this->once())->method('refresh')->with('plain:refresh')
            ->willReturn(new GitLabOAuthTokens('new-access', 'new-refresh', $expiresAt));
        $this->connections->expects($this->once())->method('save')->with($connection);

        // Act
        $token = $this->resolver->resolve($connection);

        // Assert
        Assert::assertSame('new-access', $token);
        Assert::assertSame('enc:new-access', $connection->accessTokenCiphertext());
        Assert::assertSame('enc:new-refresh', $connection->refreshTokenCiphertext());
        Assert::assertSame($expiresAt, $connection->accessTokenExpiresAt());
    }

    #[Test]
    public function skips_the_refresh_when_another_worker_already_did_it_while_waiting_for_the_lock(): void
    {
        // Arrange
        $stale = $this->connection(refreshTokenCiphertext: 'enc:refresh', expiresAt: new \DateTimeImmutable('+2 minutes'));
        $fresh = $this->connection(refreshTokenCiphertext: 'enc:refresh-2', expiresAt: new \DateTimeImmutable('+2 hours'), accessTokenCiphertext: 'enc:refreshed-by-other');

        $this->connections->expects($this->once())->method('withExclusiveLock')
            ->willReturnCallback(static fn (OrganizationId $id, \Closure $work): mixed => $work($fresh));
        $this->oauthClient->expects($this->never())->method('refresh');
        $this->connections->expects($this->never())->method('save');

        // Act
        $token = $this->resolver->resolve($stale);

        // Assert
        Assert::assertSame('plain:refreshed-by-other', $token);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->connections = $this->createMock(GitLabConnectionRepositoryInterface::class);
        $this->oauthClient = $this->createMock(GitLabOAuthClientInterface::class);

        $encryptor = $this->createStub(GitLabTokenEncryptorInterface::class);
        $encryptor->method('encrypt')->willReturnCallback(static fn (string $plain): string => 'enc:'.$plain);
        $encryptor->method('decrypt')->willReturnCallback(static fn (string $cipher): string => 'plain:'.\substr($cipher, 4));

        $this->resolver = new GitLabAccessTokenResolver($this->connections, $encryptor, $this->oauthClient);
    }

    private function connection(?string $refreshTokenCiphertext, ?\DateTimeImmutable $expiresAt, string $accessTokenCiphertext = 'enc:access'): GitLabConnection
    {
        return GitLabConnection::connect(
            id: GitLabConnectionId::generate(),
            organizationId: OrganizationId::generate(),
            baseUrl: 'https://gitlab.com',
            groupId: '1',
            groupPath: 'acme',
            groupName: 'Acme',
            accessTokenCiphertext: $accessTokenCiphertext,
            connectedByAccountId: AccountId::generate(),
            refreshTokenCiphertext: $refreshTokenCiphertext,
            accessTokenExpiresAt: $expiresAt,
        );
    }
}
