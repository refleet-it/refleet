<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\GitLabConnection\Application\Command\ConnectGitLab;

use App\Organization\GitLabConnection\Application\Command\ConnectGitLab\ConnectGitLabCommand;
use App\Organization\GitLabConnection\Application\Command\ConnectGitLab\ConnectGitLabHandler;
use App\Organization\GitLabConnection\Domain\Connection\Exception\InvalidGitLabCredentialsException;
use App\Organization\GitLabConnection\Domain\Connection\Model\GitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabApiClientInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabTokenEncryptorInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\AccountId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabConnectionId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConnectGitLabHandler::class)]
final class ConnectGitLabHandlerTest extends TestCase
{
    private GitLabConnectionRepositoryInterface&MockObject $connections;

    private GitLabApiClientInterface&MockObject $gitLabApiClient;

    private GitLabTokenEncryptorInterface&MockObject $tokenEncryptor;

    private ConnectGitLabHandler $handler;

    #[Test]
    public function connects_a_new_organization_and_saves_the_encrypted_token(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $this->connections->method('findByOrganizationId')->willReturn(null);

        $this->gitLabApiClient
            ->expects($this->once())
            ->method('resolveGroup')
            ->with('https://gitlab.com', 'glpat-secret', 'acme-corp/backend')
            ->willReturn(['id' => '42', 'name' => 'Backend', 'fullPath' => 'acme-corp/backend']);

        $this->tokenEncryptor
            ->expects($this->once())
            ->method('encrypt')
            ->with('glpat-secret')
            ->willReturn('encrypted-token');

        $savedConnection = null;
        $this->connections
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (GitLabConnection $connection) use (&$savedConnection): bool {
                $savedConnection = $connection;

                return true;
            }));

        // Act
        $result = ($this->handler)(new ConnectGitLabCommand(
            organizationId: $organizationId->asString(),
            accountId: AccountId::generate()->asString(),
            groupPath: 'acme-corp/backend',
            accessToken: 'glpat-secret',
        ));

        // Assert
        Assert::assertNotNull($savedConnection);
        Assert::assertSame('https://gitlab.com', $savedConnection->baseUrl());
        Assert::assertSame('42', $savedConnection->groupId());
        Assert::assertSame('acme-corp/backend', $savedConnection->groupPath());
        Assert::assertSame('Backend', $savedConnection->groupName());
        Assert::assertSame('encrypted-token', $savedConnection->accessTokenCiphertext());
        Assert::assertSame('https://gitlab.com', $result->baseUrl);
        Assert::assertSame('acme-corp/backend', $result->groupPath);
        Assert::assertSame('Backend', $result->groupName);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function normalizes_a_provided_self_hosted_base_url(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn(null);

        $this->gitLabApiClient
            ->expects($this->once())
            ->method('resolveGroup')
            ->with('https://gitlab.example.com', 'token', 'team')
            ->willReturn(['id' => '1', 'name' => 'Team', 'fullPath' => 'team']);

        $this->tokenEncryptor->method('encrypt')->willReturn('ciphertext');
        $this->connections->expects($this->once())->method('save');

        // Act
        $result = ($this->handler)(new ConnectGitLabCommand(
            organizationId: OrganizationId::generate()->asString(),
            accountId: AccountId::generate()->asString(),
            groupPath: 'team',
            accessToken: 'token',
            baseUrl: 'https://gitlab.example.com/',
        ));

        // Assert
        Assert::assertSame('https://gitlab.example.com', $result->baseUrl);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function reconnects_an_existing_connection_for_the_same_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $existing = GitLabConnection::connect(
            id: GitLabConnectionId::generate(),
            organizationId: $organizationId,
            baseUrl: 'https://gitlab.com',
            groupId: '1',
            groupPath: 'old-group',
            groupName: 'Old Group',
            accessTokenCiphertext: 'old-ciphertext',
            connectedByAccountId: AccountId::generate(),
        );
        $existing->recordSyncSuccess(3);

        $this->connections->method('findByOrganizationId')->willReturn($existing);

        $this->gitLabApiClient
            ->method('resolveGroup')
            ->willReturn(['id' => '2', 'name' => 'New Group', 'fullPath' => 'new-group']);

        $this->tokenEncryptor->method('encrypt')->willReturn('new-ciphertext');

        $this->connections
            ->expects($this->once())
            ->method('save')
            ->with($this->identicalTo($existing));

        // Act
        ($this->handler)(new ConnectGitLabCommand(
            organizationId: $organizationId->asString(),
            accountId: AccountId::generate()->asString(),
            groupPath: 'new-group',
            accessToken: 'new-token',
        ));

        // Assert
        Assert::assertSame('new-group', $existing->groupPath());
        Assert::assertSame('New Group', $existing->groupName());
        Assert::assertSame('new-ciphertext', $existing->accessTokenCiphertext());
        Assert::assertNull($existing->lastSyncedAt());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function propagates_invalid_credentials_and_never_saves(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn(null);

        $this->gitLabApiClient
            ->method('resolveGroup')
            ->willThrowException(new InvalidGitLabCredentialsException('the access token was rejected'));

        $this->connections->expects($this->never())->method('save');

        // Act
        $exception = null;
        try {
            ($this->handler)(new ConnectGitLabCommand(
                organizationId: OrganizationId::generate()->asString(),
                accountId: AccountId::generate()->asString(),
                groupPath: 'acme-corp/backend',
                accessToken: 'bad-token',
            ));
        } catch (\Throwable $throwable) {
            $exception = $throwable;
        }

        // Assert
        Assert::assertInstanceOf(InvalidGitLabCredentialsException::class, $exception);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->connections = $this->createMock(GitLabConnectionRepositoryInterface::class);
        $this->gitLabApiClient = $this->createMock(GitLabApiClientInterface::class);
        $this->tokenEncryptor = $this->createMock(GitLabTokenEncryptorInterface::class);

        $this->handler = new ConnectGitLabHandler(
            $this->connections,
            $this->gitLabApiClient,
            $this->tokenEncryptor,
        );
    }
}
