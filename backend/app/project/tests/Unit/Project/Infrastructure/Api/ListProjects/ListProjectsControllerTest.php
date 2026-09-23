<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Project\Infrastructure\Api\ListProjects;

use App\Project\Project\Application\Query\ListProjects\ProjectOverview;
use App\Project\Project\Application\Query\ListProjectsPage\ListProjectsPageQuery;
use App\Project\Project\Infrastructure\Api\ListProjects\ListProjectsController;
use App\Shared\Domain\Exception\AccountHasNoOrganizationException;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\OrganizationContext;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ListProjectsController::class)]
final class ListProjectsControllerTest extends TestCase
{
    private MessageBusInterface&Stub $bus;

    private OrganizationContextProviderInterface&Stub $organizationContext;

    private AccountUser $user;

    #[Test]
    public function defaults_to_a_limit_of_20_when_none_is_requested(): void
    {
        // Arrange
        $this->stubOrganization();

        $this->bus
            ->method('dispatch')
            ->willReturnCallback(static function (object $message): Envelope {
                Assert::assertInstanceOf(ListProjectsPageQuery::class, $message);
                Assert::assertSame(1, $message->page);
                Assert::assertSame(20, $message->limit);

                return new Envelope($message, [new HandledStamp(
                    ListResponse::create(items: [], totalItems: 0, pagination: PaginationParameters::fromRequest()),
                    'handler',
                )]);
            });

        $controller = new ListProjectsController($this->bus, $this->organizationContext);

        // Act
        $response = $controller($this->user, new Request());

        // Assert
        Assert::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function forwards_an_explicitly_requested_limit(): void
    {
        // Arrange
        $this->stubOrganization();

        $this->bus
            ->method('dispatch')
            ->willReturnCallback(static function (object $message): Envelope {
                Assert::assertInstanceOf(ListProjectsPageQuery::class, $message);
                Assert::assertSame(6, $message->limit);

                return new Envelope($message, [new HandledStamp(
                    ListResponse::create(items: [], totalItems: 0, pagination: PaginationParameters::fromRequest()),
                    'handler',
                )]);
            });

        $controller = new ListProjectsController($this->bus, $this->organizationContext);

        // Act
        $response = $controller($this->user, new Request(query: ['limit' => '6']));

        // Assert
        Assert::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function maps_the_returned_page_into_the_response_payload(): void
    {
        // Arrange
        $this->stubOrganization();
        $project = new ProjectOverview(
            id: 'project-1',
            name: 'Payments Service',
            externalId: '42',
            path: 'team/payments-service',
            webUrl: 'https://gitlab.com/team/payments-service',
            defaultBranch: 'main',
            description: null,
            createdAt: '2026-07-01T00:00:00+00:00',
            lastSyncedAt: null,
        );

        $this->bus
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message, [new HandledStamp(
                ListResponse::create(
                    items: [$project],
                    totalItems: 21,
                    pagination: PaginationParameters::fromRequest(),
                ),
                'handler',
            )]));

        $controller = new ListProjectsController($this->bus, $this->organizationContext);

        // Act
        $response = $controller($this->user, new Request());

        // Assert
        $payload = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        Assert::assertCount(1, $payload['projects']);
        Assert::assertSame('project-1', $payload['projects'][0]['id']);
        Assert::assertSame('Payments Service', $payload['projects'][0]['name']);
        Assert::assertSame(21, $payload['pagination']['total']);
        Assert::assertTrue($payload['pagination']['hasNextPage']);
    }

    #[Test]
    public function throws_when_the_account_has_no_organization(): void
    {
        // Arrange
        $this->organizationContext
            ->method('requireForAccount')
            ->willThrowException(new AccountHasNoOrganizationException());

        $this->bus
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $controller = new ListProjectsController($this->bus, $this->organizationContext);

        $this->expectException(AccountHasNoOrganizationException::class);

        // Act
        $controller($this->user, new Request());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->bus = $this->createStub(MessageBusInterface::class);
        $this->organizationContext = $this->createStub(OrganizationContextProviderInterface::class);
        $this->user = new AccountUser('owner@example.com', ['ROLE_USER'], null, UserId::fromString('11111111-1111-1111-1111-111111111111'));
    }

    private function stubOrganization(): void
    {
        $this->organizationContext
            ->method('requireForAccount')
            ->willReturn(new OrganizationContext(id: 'org-1', name: 'Acme', role: 'owner'));
    }
}
