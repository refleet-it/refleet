<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\GitLabConnection\Application\Command\SyncGitLabProjects;

use App\Organization\GitLabConnection\Application\Command\SyncGitLabProjects\SyncGitLabProjectsCommand;
use App\Organization\GitLabConnection\Application\Command\SyncGitLabProjects\SyncGitLabProjectsHandler;
use App\Organization\GitLabConnection\Domain\Connection\Exception\GitLabConnectionNotFoundException;
use App\Organization\GitLabConnection\Domain\Connection\Exception\InsufficientGitLabPermissionsException;
use App\Organization\GitLabConnection\Domain\Connection\Exception\InvalidGitLabCredentialsException;
use App\Organization\GitLabConnection\Domain\Connection\Model\GitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabAccessTokenResolver;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabApiClientInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabOAuthClientInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabTokenEncryptorInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\AccountId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabConnectionId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabLabel;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use App\Shared\Domain\Service\ProjectRegistryInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(SyncGitLabProjectsHandler::class)]
final class SyncGitLabProjectsHandlerTest extends TestCase
{
    private GitLabConnectionRepositoryInterface&MockObject $connections;

    private GitLabApiClientInterface&MockObject $gitLabApiClient;

    private GitLabTokenEncryptorInterface&MockObject $tokenEncryptor;

    private ProjectRegistryInterface&MockObject $projectRegistry;

    private GitLabConnection $connection;

    private SyncGitLabProjectsHandler $handler;

    #[Test]
    public function syncs_every_project_and_falls_back_to_project_webhooks_when_the_group_refuses_one(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn($this->connection);
        $this->tokenEncryptor->method('decrypt')->with('ciphertext')->willReturn('plaintext-token');
        $this->gitLabApiClient->method('listGroupProjects')->willReturn([
            ['id' => 1, 'name' => 'Payments', 'path_with_namespace' => 'acme/payments', 'web_url' => 'https://gitlab.com/acme/payments', 'default_branch' => 'main', 'description' => null],
            ['id' => 2, 'name' => 'Billing', 'path_with_namespace' => 'acme/billing', 'web_url' => 'https://gitlab.com/acme/billing', 'default_branch' => 'main', 'description' => 'Handles billing'],
        ]);

        $registered = [];
        $this->projectRegistry
            ->expects($this->exactly(2))
            ->method('register')
            ->willReturnCallback(static function (string $organizationId, string $externalId, string $name, string $path) use (&$registered): void {
                $registered[] = ['externalId' => $externalId, 'path' => $path];
            });

        $archivedFor = null;
        $this->projectRegistry
            ->expects($this->once())
            ->method('archiveMissing')
            ->willReturnCallback(static function (string $organizationId, array $seenExternalIds) use (&$archivedFor): int {
                $archivedFor = $seenExternalIds;

                return 3;
            });

        $this->connections->expects($this->once())->method('save')->with($this->identicalTo($this->connection));

        $this->gitLabApiClient->method('ensureGroupWebhook')->willReturn(false);
        $this->gitLabApiClient->expects($this->never())->method('removeProjectWebhook');

        $registeredWebhooks = [];
        $this->gitLabApiClient
            ->expects($this->exactly(2))
            ->method('ensureProjectWebhook')
            ->willReturnCallback(function (string $baseUrl, string $accessToken, string $externalProjectId, string $webhookUrl, string $secretToken) use (&$registeredWebhooks): void {
                $registeredWebhooks[] = $externalProjectId;
                Assert::assertSame('plaintext-token', $accessToken);
                Assert::assertSame(
                    'http://localhost/api/webhooks/gitlab/'.$this->connection->organizationId()->asString(),
                    $webhookUrl,
                );
                Assert::assertSame($this->connection->webhookSecret(), $secretToken);
            });

        // Act
        $result = ($this->handler)(new SyncGitLabProjectsCommand(organizationId: $this->connection->organizationId()->asString()));

        // Assert
        Assert::assertSame(2, $result->syncedCount);
        Assert::assertSame(0, $result->failedCount);
        Assert::assertSame(3, $result->archivedCount);
        Assert::assertCount(2, $registered);
        Assert::assertSame('1', $registered[0]['externalId']);
        Assert::assertSame('acme/payments', $registered[0]['path']);
        Assert::assertSame(['1', '2'], $archivedFor);
        Assert::assertSame('success', $this->connection->lastSyncStatus()->value);
        Assert::assertSame(2, $this->connection->lastSyncProjectCount());
        Assert::assertSame(['1', '2'], $registeredWebhooks);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function registers_one_group_webhook_and_removes_stale_project_webhooks_when_gitlab_allows_it(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn($this->connection);
        $this->tokenEncryptor->method('decrypt')->willReturn('plaintext-token');
        $this->gitLabApiClient->method('listGroupProjects')->willReturn([
            ['id' => 1, 'name' => 'Payments', 'path_with_namespace' => 'acme/payments'],
            ['id' => 2, 'name' => 'Billing', 'path_with_namespace' => 'acme/billing'],
        ]);
        $this->projectRegistry->method('archiveMissing')->willReturn(0);

        $webhookUrl = 'http://localhost/api/webhooks/gitlab/'.$this->connection->organizationId()->asString();

        $this->gitLabApiClient
            ->expects($this->once())
            ->method('ensureGroupWebhook')
            ->with('https://gitlab.com', 'plaintext-token', '99', $webhookUrl, $this->connection->webhookSecret())
            ->willReturn(true);
        $this->gitLabApiClient->expects($this->never())->method('ensureProjectWebhook');

        $removedFrom = [];
        $this->gitLabApiClient
            ->expects($this->exactly(2))
            ->method('removeProjectWebhook')
            ->willReturnCallback(static function (string $baseUrl, string $accessToken, string $externalProjectId, string $url) use (&$removedFrom, $webhookUrl): void {
                Assert::assertSame($webhookUrl, $url);
                $removedFrom[] = $externalProjectId;
            });

        // Act
        $result = ($this->handler)(new SyncGitLabProjectsCommand(organizationId: $this->connection->organizationId()->asString()));

        // Assert
        Assert::assertSame(2, $result->syncedCount);
        Assert::assertSame(['1', '2'], $removedFrom);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function falls_back_to_project_webhooks_when_the_group_webhook_call_blows_up(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn($this->connection);
        $this->tokenEncryptor->method('decrypt')->willReturn('plaintext-token');
        $this->gitLabApiClient->method('listGroupProjects')->willReturn([
            ['id' => 1, 'name' => 'Payments', 'path_with_namespace' => 'acme/payments'],
        ]);
        $this->projectRegistry->method('archiveMissing')->willReturn(0);

        $this->gitLabApiClient
            ->method('ensureGroupWebhook')
            ->willThrowException(new InvalidGitLabCredentialsException('the GitLab instance could not be reached while listing the webhook'));
        $this->gitLabApiClient->expects($this->once())->method('ensureProjectWebhook');
        $this->gitLabApiClient->expects($this->never())->method('removeProjectWebhook');

        // Act
        $result = ($this->handler)(new SyncGitLabProjectsCommand(organizationId: $this->connection->organizationId()->asString()));

        // Assert
        Assert::assertSame(1, $result->syncedCount);
        Assert::assertSame('success', $this->connection->lastSyncStatus()->value);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function a_failed_stale_project_webhook_removal_does_not_fail_the_project_sync(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn($this->connection);
        $this->tokenEncryptor->method('decrypt')->willReturn('plaintext-token');
        $this->gitLabApiClient->method('listGroupProjects')->willReturn([
            ['id' => 1, 'name' => 'Payments', 'path_with_namespace' => 'acme/payments'],
        ]);
        $this->projectRegistry->method('archiveMissing')->willReturn(0);

        $this->gitLabApiClient->method('ensureGroupWebhook')->willReturn(true);
        $this->gitLabApiClient
            ->method('removeProjectWebhook')
            ->willThrowException(new InvalidGitLabCredentialsException('GitLab responded with status 500'));

        // Act
        $result = ($this->handler)(new SyncGitLabProjectsCommand(organizationId: $this->connection->organizationId()->asString()));

        // Assert
        Assert::assertSame(1, $result->syncedCount);
        Assert::assertSame(0, $result->failedCount);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function a_failed_webhook_registration_does_not_fail_the_project_sync(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn($this->connection);
        $this->tokenEncryptor->method('decrypt')->willReturn('plaintext-token');
        $this->gitLabApiClient->method('listGroupProjects')->willReturn([
            ['id' => 1, 'name' => 'Payments', 'path_with_namespace' => 'acme/payments'],
        ]);
        $this->gitLabApiClient
            ->method('ensureProjectWebhook')
            ->willThrowException(new InvalidGitLabCredentialsException('group webhooks require a paid GitLab tier'));

        $this->projectRegistry->method('archiveMissing')->willReturn(0);

        // Act
        $result = ($this->handler)(new SyncGitLabProjectsCommand(organizationId: $this->connection->organizationId()->asString()));

        // Assert
        Assert::assertSame(1, $result->syncedCount);
        Assert::assertSame(0, $result->failedCount);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function continues_after_a_single_project_registration_failure(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn($this->connection);
        $this->tokenEncryptor->method('decrypt')->willReturn('plaintext-token');
        $this->gitLabApiClient->method('listGroupProjects')->willReturn([
            ['id' => 1, 'name' => 'Payments', 'path_with_namespace' => 'acme/payments'],
            ['id' => 2, 'name' => 'Billing', 'path_with_namespace' => 'acme/billing'],
        ]);

        $callCount = 0;
        $this->projectRegistry
            ->expects($this->exactly(2))
            ->method('register')
            ->willReturnCallback(static function () use (&$callCount): void {
                ++$callCount;

                if (2 === $callCount) {
                    throw new \RuntimeException('database is down');
                }
            });
        $this->projectRegistry->method('archiveMissing')->willReturn(0);

        $this->connections->expects($this->once())->method('save');

        // Act
        $result = ($this->handler)(new SyncGitLabProjectsCommand(organizationId: $this->connection->organizationId()->asString()));

        // Assert
        Assert::assertSame(1, $result->syncedCount);
        Assert::assertSame(1, $result->failedCount);
        Assert::assertSame('success', $this->connection->lastSyncStatus()->value);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function ensures_the_refleet_label_exists_in_the_group_on_every_sync(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn($this->connection);
        $this->tokenEncryptor->method('decrypt')->willReturn('plaintext-token');
        $this->gitLabApiClient->method('listGroupProjects')->willReturn([
            ['id' => 1, 'name' => 'Payments', 'path_with_namespace' => 'acme/payments'],
        ]);
        $this->projectRegistry->method('archiveMissing')->willReturn(0);

        $this->gitLabApiClient
            ->expects($this->once())
            ->method('ensureGroupLabel')
            ->with($this->connection->baseUrl(), 'plaintext-token', $this->connection->groupId(), GitLabLabel::refleet());

        // Act
        ($this->handler)(new SyncGitLabProjectsCommand(organizationId: $this->connection->organizationId()->asString()));

        // Assert
        Assert::assertSame('success', $this->connection->lastSyncStatus()->value);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function marks_the_sync_failed_when_the_token_cannot_manage_group_labels(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn($this->connection);
        $this->tokenEncryptor->method('decrypt')->willReturn('plaintext-token');
        $this->gitLabApiClient->method('listGroupProjects')->willReturn([
            ['id' => 1, 'name' => 'Payments', 'path_with_namespace' => 'acme/payments'],
        ]);
        $this->projectRegistry->expects($this->once())->method('register');
        $this->projectRegistry->method('archiveMissing')->willReturn(0);
        $this->gitLabApiClient
            ->method('ensureGroupLabel')
            ->willThrowException(new InsufficientGitLabPermissionsException('managing labels in group "99" needs at least the Reporter role'));

        // Act
        $result = ($this->handler)(new SyncGitLabProjectsCommand(organizationId: $this->connection->organizationId()->asString()));

        // Assert
        Assert::assertSame(1, $result->syncedCount);
        Assert::assertSame('failed', $this->connection->lastSyncStatus()->value);
        Assert::assertStringContainsString('Reporter role', (string) $this->connection->lastSyncError());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function records_failure_and_rethrows_when_gitlab_rejects_the_stored_token(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn($this->connection);
        $this->tokenEncryptor->method('decrypt')->willReturn('plaintext-token');
        $this->gitLabApiClient
            ->method('listGroupProjects')
            ->willThrowException(new InvalidGitLabCredentialsException('the access token was rejected'));

        $this->projectRegistry->expects($this->never())->method('register');
        $this->projectRegistry->expects($this->never())->method('archiveMissing');
        $this->connections->expects($this->once())->method('save')->with($this->identicalTo($this->connection));

        // Assert
        $this->expectException(InvalidGitLabCredentialsException::class);

        // Act
        ($this->handler)(new SyncGitLabProjectsCommand(organizationId: $this->connection->organizationId()->asString()));

        Assert::assertSame('failed', $this->connection->lastSyncStatus()->value);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throws_when_the_organization_has_no_connection(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn(null);

        // Assert
        $this->expectException(GitLabConnectionNotFoundException::class);

        // Act
        ($this->handler)(new SyncGitLabProjectsCommand(organizationId: OrganizationId::generate()->asString()));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->connections = $this->createMock(GitLabConnectionRepositoryInterface::class);
        $this->gitLabApiClient = $this->createMock(GitLabApiClientInterface::class);
        $this->tokenEncryptor = $this->createMock(GitLabTokenEncryptorInterface::class);
        $this->projectRegistry = $this->createMock(ProjectRegistryInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $this->connection = GitLabConnection::connect(
            id: GitLabConnectionId::generate(),
            organizationId: OrganizationId::generate(),
            baseUrl: 'https://gitlab.com',
            groupId: '99',
            groupPath: 'acme',
            groupName: 'Acme',
            accessTokenCiphertext: 'ciphertext',
            connectedByAccountId: AccountId::generate(),
        );

        $this->handler = new SyncGitLabProjectsHandler(
            $this->connections,
            $this->gitLabApiClient,
            new GitLabAccessTokenResolver($this->connections, $this->tokenEncryptor, $this->createStub(GitLabOAuthClientInterface::class)),
            $this->projectRegistry,
            $logger,
            'http://localhost',
        );
    }
}
