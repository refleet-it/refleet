<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\GitLabConnection\Application\Query\GetGitLabConnection;

use App\Organization\GitLabConnection\Application\Query\GetGitLabConnection\GetGitLabConnectionHandler;
use App\Organization\GitLabConnection\Application\Query\GetGitLabConnection\GetGitLabConnectionQuery;
use App\Organization\GitLabConnection\Domain\Connection\Model\GitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\AccountId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabConnectionId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetGitLabConnectionHandler::class)]
final class GetGitLabConnectionHandlerTest extends TestCase
{
    private GitLabConnectionRepositoryInterface&Stub $connections;

    private GetGitLabConnectionHandler $handler;

    #[Test]
    public function returns_null_when_the_organization_has_no_connection(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn(null);

        // Act
        $result = ($this->handler)(new GetGitLabConnectionQuery(organizationId: OrganizationId::generate()->asString()));

        // Assert
        Assert::assertNull($result);
    }

    #[Test]
    public function returns_an_overview_of_an_existing_connection(): void
    {
        // Arrange
        $connection = GitLabConnection::connect(
            id: GitLabConnectionId::generate(),
            organizationId: OrganizationId::generate(),
            baseUrl: 'https://gitlab.com',
            groupId: '1',
            groupPath: 'acme-corp',
            groupName: 'Acme Corp',
            accessTokenCiphertext: 'ciphertext',
            connectedByAccountId: AccountId::generate(),
        );
        $connection->recordSyncSuccess(7);

        $this->connections->method('findByOrganizationId')->willReturn($connection);

        // Act
        $result = ($this->handler)(new GetGitLabConnectionQuery(organizationId: OrganizationId::generate()->asString()));

        // Assert
        Assert::assertNotNull($result);
        Assert::assertSame('https://gitlab.com', $result->baseUrl);
        Assert::assertSame('acme-corp', $result->groupPath);
        Assert::assertSame('Acme Corp', $result->groupName);
        Assert::assertSame('success', $result->lastSyncStatus);
        Assert::assertSame(7, $result->lastSyncProjectCount);
        Assert::assertNull($result->lastSyncError);
        Assert::assertSame('access_token', $result->authMethod);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->connections = $this->createStub(GitLabConnectionRepositoryInterface::class);
        $this->handler = new GetGitLabConnectionHandler($this->connections);
    }
}
