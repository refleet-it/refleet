<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\GitLabConnection\Infrastructure\Api\GetGitLabConnection;

use App\Organization\GitLabConnection\Application\Query\GetGitLabConnection\GetGitLabConnectionQuery;
use App\Organization\GitLabConnection\Application\Query\GetGitLabConnection\GitLabConnectionOverview;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabOAuthClientInterface;
use App\Organization\GitLabConnection\Infrastructure\Api\GetGitLabConnection\GetGitLabConnectionController;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\OrganizationContext;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(GetGitLabConnectionController::class)]
final class GetGitLabConnectionControllerTest extends TestCase
{
    private MessageBusInterface&Stub $bus;

    private OrganizationContextProviderInterface&Stub $organizationContext;

    private AccountUser $user;

    #[Test]
    public function reports_not_connected_when_the_organization_has_no_connection(): void
    {
        // Arrange
        $this->stubOrganization();

        $this->bus
            ->method('dispatch')
            ->willReturnCallback(static function (object $message): Envelope {
                Assert::assertInstanceOf(GetGitLabConnectionQuery::class, $message);

                return new Envelope($message, [new HandledStamp(null, 'handler')]);
            });

        $controller = new GetGitLabConnectionController($this->bus, $this->organizationContext, $this->oauthClient());

        // Act
        $response = $controller($this->user);

        // Assert
        $payload = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame(['connected' => false, 'oauthAvailable' => true], $payload);
    }

    #[Test]
    public function exposes_only_the_connection_fields_the_settings_page_needs(): void
    {
        // Arrange
        $this->stubOrganization();
        $connection = new GitLabConnectionOverview(
            baseUrl: 'https://gitlab.com',
            groupPath: 'acme-corp',
            groupName: 'Acme Corp',
            connectedAt: '2026-08-02T00:00:00+00:00',
            lastSyncedAt: null,
            lastSyncStatus: 'never_synced',
            lastSyncError: null,
            lastSyncProjectCount: null,
            authMethod: 'access_token',
        );

        $this->bus
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message, [new HandledStamp($connection, 'handler')]));

        $controller = new GetGitLabConnectionController($this->bus, $this->organizationContext, $this->oauthClient());

        // Act
        $response = $controller($this->user);

        // Assert
        $payload = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertTrue($payload['connected']);
        Assert::assertSame('acme-corp', $payload['groupPath']);
        Assert::assertArrayNotHasKey('accessToken', $payload);
        Assert::assertSame('access_token', $payload['authMethod']);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->bus = $this->createStub(MessageBusInterface::class);
        $this->organizationContext = $this->createStub(OrganizationContextProviderInterface::class);
        $this->user = new AccountUser('owner@example.com', ['ROLE_USER'], null, UserId::fromString('11111111-1111-1111-1111-111111111111'));
    }

    private function oauthClient(): GitLabOAuthClientInterface
    {
        $client = $this->createStub(GitLabOAuthClientInterface::class);
        $client->method('isConfigured')->willReturn(true);

        return $client;
    }

    private function stubOrganization(): void
    {
        $this->organizationContext
            ->method('requireForAccount')
            ->willReturn(new OrganizationContext(id: 'org-1', name: 'Acme', role: 'owner'));
    }
}
