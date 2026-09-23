<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\GitLabConnection\Application\Command\ConnectGitLabViaOAuth;

use App\Organization\GitLabConnection\Application\Command\ConnectGitLabViaOAuth\ConnectGitLabViaOAuthCommand;
use App\Organization\GitLabConnection\Application\Command\ConnectGitLabViaOAuth\ConnectGitLabViaOAuthHandler;
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
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConnectGitLabViaOAuthHandler::class)]
final class ConnectGitLabViaOAuthHandlerTest extends TestCase
{
    private GitLabConnectionRepositoryInterface&MockObject $connections;

    private GitLabApiClientInterface&Stub $gitLabApiClient;

    private GitLabOAuthClientInterface&Stub $oauthClient;

    private GitLabTokenEncryptorInterface&Stub $encryptor;

    private ConnectGitLabViaOAuthHandler $handler;

    #[Test]
    public function refuses_when_oauth_is_not_configured(): void
    {
        // Arrange
        $this->oauthClient->method('isConfigured')->willReturn(false);
        $this->connections->expects($this->never())->method('save');

        // Assert
        $this->expectException(GitLabOAuthNotConfiguredException::class);

        // Act
        ($this->handler)($this->command());
    }

    #[Test]
    public function exchanges_the_code_resolves_the_group_and_stores_both_tokens_encrypted(): void
    {
        // Arrange
        $expiresAt = new \DateTimeImmutable('+2 hours');
        $oauthClient = $this->createMock(GitLabOAuthClientInterface::class);
        $oauthClient->method('isConfigured')->willReturn(true);
        $oauthClient->method('baseUrl')->willReturn('https://gitlab.com');
        $oauthClient->expects($this->once())->method('exchangeCode')->with('the-code')
            ->willReturn(new GitLabOAuthTokens('access', 'refresh', $expiresAt));
        $gitLabApiClient = $this->createMock(GitLabApiClientInterface::class);
        $gitLabApiClient->expects($this->once())->method('resolveGroup')->with('https://gitlab.com', 'access', 'acme/backend')
            ->willReturn(['id' => '42', 'name' => 'Backend', 'fullPath' => 'acme/backend']);
        $this->connections->method('findByOrganizationId')->willReturn(null);
        $handler = new ConnectGitLabViaOAuthHandler($this->connections, $gitLabApiClient, $oauthClient, $this->encryptor);

        $saved = null;
        $this->connections->expects($this->once())->method('save')
            ->willReturnCallback(static function (GitLabConnection $connection) use (&$saved): void {
                $saved = $connection;
            });

        // Act
        $result = $handler($this->command());

        // Assert
        Assert::assertInstanceOf(GitLabConnection::class, $saved);
        Assert::assertSame('enc:access', $saved->accessTokenCiphertext());
        Assert::assertSame('enc:refresh', $saved->refreshTokenCiphertext());
        Assert::assertSame($expiresAt, $saved->accessTokenExpiresAt());
        Assert::assertTrue($saved->usesOAuth());
        Assert::assertSame('42', $saved->groupId());
        Assert::assertSame('acme/backend', $result->groupPath);
        Assert::assertSame('https://gitlab.com', $result->baseUrl);
    }

    #[Test]
    public function reconnects_an_existing_connection_in_place(): void
    {
        // Arrange
        $existing = GitLabConnection::connect(
            id: GitLabConnectionId::generate(),
            organizationId: OrganizationId::generate(),
            baseUrl: 'https://gitlab.example.com',
            groupId: '1',
            groupPath: 'old',
            groupName: 'Old',
            accessTokenCiphertext: 'enc:old-pat',
            connectedByAccountId: AccountId::generate(),
        );
        $this->oauthClient->method('isConfigured')->willReturn(true);
        $this->oauthClient->method('baseUrl')->willReturn('https://gitlab.com');
        $this->oauthClient->method('exchangeCode')->willReturn(new GitLabOAuthTokens('access', 'refresh', new \DateTimeImmutable('+2 hours')));
        $this->gitLabApiClient->method('resolveGroup')->willReturn(['id' => '42', 'name' => 'Backend', 'fullPath' => 'acme/backend']);
        $this->connections->method('findByOrganizationId')->willReturn($existing);
        $this->connections->expects($this->once())->method('save')->with($existing);

        // Act
        ($this->handler)($this->command());

        // Assert
        Assert::assertSame('https://gitlab.com', $existing->baseUrl());
        Assert::assertSame('acme/backend', $existing->groupPath());
        Assert::assertSame('enc:access', $existing->accessTokenCiphertext());
        Assert::assertTrue($existing->usesOAuth());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->connections = $this->createMock(GitLabConnectionRepositoryInterface::class);
        $this->gitLabApiClient = $this->createStub(GitLabApiClientInterface::class);
        $this->oauthClient = $this->createStub(GitLabOAuthClientInterface::class);

        $this->encryptor = $this->createStub(GitLabTokenEncryptorInterface::class);
        $this->encryptor->method('encrypt')->willReturnCallback(static fn (string $plain): string => 'enc:'.$plain);

        $this->handler = new ConnectGitLabViaOAuthHandler($this->connections, $this->gitLabApiClient, $this->oauthClient, $this->encryptor);
    }

    private function command(): ConnectGitLabViaOAuthCommand
    {
        return new ConnectGitLabViaOAuthCommand(
            organizationId: OrganizationId::generate()->asString(),
            accountId: AccountId::generate()->asString(),
            code: 'the-code',
            groupPath: 'acme/backend',
        );
    }
}
