<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Application\Query\ListPendingInvitations;

use App\Organization\Organization\Application\Query\ListPendingInvitations\ListPendingInvitationsHandler;
use App\Organization\Organization\Application\Query\ListPendingInvitations\ListPendingInvitationsQuery;
use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\NotOrganizationOwnerException;
use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Invitation\Model\Invitation;
use App\Organization\Organization\Domain\Invitation\Repository\InvitationRepositoryInterface;
use App\Organization\Organization\Domain\Invitation\ValueObject\InvitationId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListPendingInvitationsHandler::class)]
final class ListPendingInvitationsHandlerTest extends TestCase
{
    private EmployeeRepositoryInterface&Stub $employees;

    private InvitationRepositoryInterface&MockObject $invitations;

    private ListPendingInvitationsHandler $handler;

    #[Test]
    public function owner_lists_pending_invitations_for_their_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization($organizationId, RoleEnum::OWNER);

        $invitation = Invitation::create(
            id: InvitationId::generate(),
            organizationId: $organizationId,
            email: 'invitee@example.com',
            role: RoleEnum::USER,
            invitedByAccountId: $owner->accountId()->asString(),
            token: 'a-token',
            expiresAt: new \DateTimeImmutable('+7 days'),
        );

        $pagination = PaginationParameters::fromRequest();

        $this->employees->method('findByAccountId')->willReturn($owner);
        $this->invitations
            ->expects($this->once())
            ->method('getPendingPaginatedList')
            ->with($organizationId, $this->anything(), $this->anything())
            ->willReturn(ListResponse::create([$invitation], 1, $pagination));

        // Act
        $result = ($this->handler)(new ListPendingInvitationsQuery($owner->accountId()->asString()));

        // Assert
        Assert::assertCount(1, $result->getItems());
        Assert::assertSame('invitee@example.com', $result->getItems()[0]->email);
        Assert::assertSame($invitation->id()->asString(), $result->getItems()[0]->id);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throws_when_requester_is_not_the_owner(): void
    {
        // Arrange
        $member = Employee::mirror(AccountId::generate(), 'member@example.com');
        $member->joinOrganization(OrganizationId::generate(), RoleEnum::USER);
        $this->employees->method('findByAccountId')->willReturn($member);

        // Assert
        $this->expectException(NotOrganizationOwnerException::class);

        // Act
        ($this->handler)(new ListPendingInvitationsQuery($member->accountId()->asString()));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->employees = $this->createStub(EmployeeRepositoryInterface::class);
        $this->invitations = $this->createMock(InvitationRepositoryInterface::class);
        $this->handler = new ListPendingInvitationsHandler($this->employees, $this->invitations);
    }
}
