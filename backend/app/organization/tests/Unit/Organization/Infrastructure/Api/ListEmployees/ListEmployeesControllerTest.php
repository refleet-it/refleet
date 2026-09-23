<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Infrastructure\Api\ListEmployees;

use App\Organization\Organization\Application\Query\ListEmployees\EmployeeOverview;
use App\Organization\Organization\Application\Query\ListEmployees\ListEmployeesQuery;
use App\Organization\Organization\Infrastructure\Api\ListEmployees\ListEmployeesController;
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

#[CoversClass(ListEmployeesController::class)]
final class ListEmployeesControllerTest extends TestCase
{
    private MessageBusInterface&Stub $bus;

    private OrganizationContextProviderInterface&Stub $organizationContext;

    private AccountUser $user;

    #[Test]
    public function maps_the_returned_page_into_the_response_payload(): void
    {
        // Arrange
        $this->stubOrganization();
        $employee = new EmployeeOverview(
            accountId: 'account-1',
            email: 'owner@example.com',
            role: 'owner',
            joinedAt: '2026-07-01T00:00:00+00:00',
        );

        $this->bus
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use ($employee): Envelope {
                Assert::assertInstanceOf(ListEmployeesQuery::class, $message);
                Assert::assertSame('org-1', $message->organizationId);

                return new Envelope($message, [new HandledStamp(
                    ListResponse::create(items: [$employee], totalItems: 1, pagination: PaginationParameters::fromRequest()),
                    'handler',
                )]);
            });

        $controller = new ListEmployeesController($this->bus, $this->organizationContext);

        // Act
        $response = $controller($this->user, new Request());

        // Assert
        $payload = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        Assert::assertCount(1, $payload['employees']);
        Assert::assertSame('owner@example.com', $payload['employees'][0]['email']);
        Assert::assertSame(1, $payload['pagination']['total']);
        Assert::assertFalse($payload['pagination']['hasNextPage']);
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

        $controller = new ListEmployeesController($this->bus, $this->organizationContext);

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
