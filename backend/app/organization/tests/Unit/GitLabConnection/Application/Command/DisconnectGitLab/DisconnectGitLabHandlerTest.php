<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\GitLabConnection\Application\Command\DisconnectGitLab;

use App\Organization\GitLabConnection\Application\Command\DisconnectGitLab\DisconnectGitLabCommand;
use App\Organization\GitLabConnection\Application\Command\DisconnectGitLab\DisconnectGitLabHandler;
use App\Organization\GitLabConnection\Domain\Connection\Exception\GitLabConnectionNotFoundException;
use App\Organization\GitLabConnection\Domain\Connection\Model\GitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\AccountId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabConnectionId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DisconnectGitLabHandler::class)]
final class DisconnectGitLabHandlerTest extends TestCase
{
    private GitLabConnectionRepositoryInterface&MockObject $connections;

    private DisconnectGitLabHandler $handler;

    #[Test]
    public function removes_the_connection_for_the_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $connection = GitLabConnection::connect(
            id: GitLabConnectionId::generate(),
            organizationId: $organizationId,
            baseUrl: 'https://gitlab.com',
            groupId: '1',
            groupPath: 'acme-corp',
            groupName: 'Acme Corp',
            accessTokenCiphertext: 'ciphertext',
            connectedByAccountId: AccountId::generate(),
        );

        $this->connections->method('findByOrganizationId')->willReturn($connection);
        $this->connections
            ->expects($this->once())
            ->method('remove')
            ->with($this->identicalTo($connection));

        // Act
        ($this->handler)(new DisconnectGitLabCommand(organizationId: $organizationId->asString()));
    }

    #[Test]
    public function throws_when_no_connection_exists(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn(null);
        $this->connections->expects($this->never())->method('remove');

        // Act
        $exception = null;
        try {
            ($this->handler)(new DisconnectGitLabCommand(organizationId: OrganizationId::generate()->asString()));
        } catch (\Throwable $throwable) {
            $exception = $throwable;
        }

        // Assert
        Assert::assertInstanceOf(GitLabConnectionNotFoundException::class, $exception);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->connections = $this->createMock(GitLabConnectionRepositoryInterface::class);
        $this->handler = new DisconnectGitLabHandler($this->connections);
    }
}
