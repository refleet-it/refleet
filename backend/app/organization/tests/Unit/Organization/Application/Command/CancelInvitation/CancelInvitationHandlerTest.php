<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Application\Command\CancelInvitation;

use App\Organization\Organization\Application\Command\CancelInvitation\CancelInvitationCommand;
use App\Organization\Organization\Application\Command\CancelInvitation\CancelInvitationHandler;
use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\NotOrganizationOwnerException;
use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Invitation\Exception\InvitationNotFoundException;
use App\Organization\Organization\Domain\Invitation\Exception\InvitationNotPendingException;
use App\Organization\Organization\Domain\Invitation\Model\Invitation;
use App\Organization\Organization\Domain\Invitation\Repository\InvitationRepositoryInterface;
use App\Organization\Organization\Domain\Invitation\ValueObject\InvitationId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(CancelInvitationHandler::class)]
final class CancelInvitationHandlerTest extends TestCase
{
    private EmployeeRepositoryInterface&Stub $employees;

    private InvitationRepositoryInterface&MockObject $invitations;

    private CancelInvitationHandler $handler;

    #[Test]
    public function owner_cancels_a_pending_invitation(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization($organizationId, RoleEnum::OWNER);

        $invitation = $this->pendingInvitation($organizationId, $owner);

        $this->employees->method('findByAccountId')->willReturn($owner);
        $this->invitations->method('findById')->willReturn($invitation);
        $this->invitations->expects($this->once())->method('save')->with($invitation);

        // Act
        ($this->handler)(new CancelInvitationCommand($owner->accountId()->asString(), $invitation->id()->asString()));

        // Assert
        Assert::assertTrue($invitation->isCancelled());
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
        ($this->handler)(new CancelInvitationCommand($member->accountId()->asString(), InvitationId::generate()->asString()));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throws_when_invitation_belongs_to_another_organization(): void
    {
        // Arrange
        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization(OrganizationId::generate(), RoleEnum::OWNER);

        $invitation = $this->pendingInvitation(OrganizationId::generate(), $owner);

        $this->employees->method('findByAccountId')->willReturn($owner);
        $this->invitations->method('findById')->willReturn($invitation);

        // Assert
        $this->expectException(InvitationNotFoundException::class);

        // Act
        ($this->handler)(new CancelInvitationCommand($owner->accountId()->asString(), $invitation->id()->asString()));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throws_when_invitation_is_already_accepted(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization($organizationId, RoleEnum::OWNER);

        $invitation = $this->pendingInvitation($organizationId, $owner);
        $invitation->accept();

        $this->employees->method('findByAccountId')->willReturn($owner);
        $this->invitations->method('findById')->willReturn($invitation);

        // Assert
        $this->expectException(InvitationNotPendingException::class);

        // Act
        ($this->handler)(new CancelInvitationCommand($owner->accountId()->asString(), $invitation->id()->asString()));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->employees = $this->createStub(EmployeeRepositoryInterface::class);
        $this->invitations = $this->createMock(InvitationRepositoryInterface::class);
        $this->handler = new CancelInvitationHandler($this->employees, $this->invitations);
    }

    private function pendingInvitation(OrganizationId $organizationId, Employee $owner): Invitation
    {
        return Invitation::create(
            id: InvitationId::generate(),
            organizationId: $organizationId,
            email: 'invitee@example.com',
            role: RoleEnum::USER,
            invitedByAccountId: $owner->accountId()->asString(),
            token: 'a-token',
            expiresAt: new \DateTimeImmutable('+7 days'),
        );
    }
}
