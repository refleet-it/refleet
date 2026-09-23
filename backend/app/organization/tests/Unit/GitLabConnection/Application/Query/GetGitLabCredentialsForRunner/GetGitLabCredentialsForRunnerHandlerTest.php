<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\GitLabConnection\Application\Query\GetGitLabCredentialsForRunner;

use App\Organization\GitLabConnection\Application\Query\GetGitLabCredentialsForRunner\GetGitLabCredentialsForRunnerHandler;
use App\Organization\GitLabConnection\Application\Query\GetGitLabCredentialsForRunner\GetGitLabCredentialsForRunnerQuery;
use App\Organization\GitLabConnection\Domain\Connection\Exception\GitLabConnectionNotFoundException;
use App\Organization\GitLabConnection\Domain\Connection\Model\GitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabAccessTokenResolver;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabOAuthClientInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabTokenEncryptorInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\AccountId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabConnectionId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetGitLabCredentialsForRunnerHandler::class)]
final class GetGitLabCredentialsForRunnerHandlerTest extends TestCase
{
    private GitLabConnectionRepositoryInterface&Stub $connections;

    private GitLabTokenEncryptorInterface&MockObject $tokenEncryptor;

    private GetGitLabCredentialsForRunnerHandler $handler;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throws_when_the_organization_has_no_connection(): void
    {
        // Arrange
        $this->connections->method('findByOrganizationId')->willReturn(null);

        // Assert
        $this->expectException(GitLabConnectionNotFoundException::class);

        // Act
        ($this->handler)(new GetGitLabCredentialsForRunnerQuery(organizationId: OrganizationId::generate()->asString()));
    }

    #[Test]
    public function returns_the_base_url_and_decrypted_access_token_of_an_existing_connection(): void
    {
        // Arrange
        $connection = GitLabConnection::connect(
            id: GitLabConnectionId::generate(),
            organizationId: OrganizationId::generate(),
            baseUrl: 'https://gitlab.example.com',
            groupId: '1',
            groupPath: 'acme-robotics',
            groupName: 'Acme Robotics',
            accessTokenCiphertext: 'ciphertext',
            connectedByAccountId: AccountId::generate(),
        );

        $this->connections->method('findByOrganizationId')->willReturn($connection);
        $this->tokenEncryptor->method('decrypt')->with('ciphertext')->willReturn('glpat-super-secret');

        // Act
        $result = ($this->handler)(new GetGitLabCredentialsForRunnerQuery(organizationId: OrganizationId::generate()->asString()));

        // Assert
        Assert::assertSame('https://gitlab.example.com', $result->baseUrl);
        Assert::assertSame('glpat-super-secret', $result->accessToken);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->connections = $this->createStub(GitLabConnectionRepositoryInterface::class);
        $this->tokenEncryptor = $this->createMock(GitLabTokenEncryptorInterface::class);
        $this->handler = new GetGitLabCredentialsForRunnerHandler(
            $this->connections,
            new GitLabAccessTokenResolver($this->connections, $this->tokenEncryptor, $this->createStub(GitLabOAuthClientInterface::class)),
        );
    }
}
