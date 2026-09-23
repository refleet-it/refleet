<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Application\Command\AcceptInvitation;

use App\Organization\Organization\Application\Command\AcceptInvitation\AcceptInvitationCommand;
use App\Organization\Organization\Application\Command\AcceptInvitation\AcceptInvitationHandler;
use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Invitation\Exception\InvitationAlreadyAcceptedException;
use App\Organization\Organization\Domain\Invitation\Exception\InvitationExpiredException;
use App\Organization\Organization\Domain\Invitation\Exception\InvitationNotFoundException;
use App\Organization\Organization\Domain\Invitation\Model\Invitation;
use App\Organization\Organization\Domain\Invitation\Repository\InvitationRepositoryInterface;
use App\Organization\Organization\Domain\Invitation\ValueObject\InvitationId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\Service\InvitedAccountRegistrarInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AcceptInvitationHandler::class)]
final class AcceptInvitationHandlerTest extends TestCase
{
    private InvitationRepositoryInterface&MockObject $invitations;

    private EmployeeRepositoryInterface&MockObject $employees;

    private InvitedAccountRegistrarInterface&MockObject $accountRegistrar;

    private AcceptInvitationHandler $handler;

    #[Test]
    public function accepts_a_pending_invitation_and_joins_the_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $invitation = $this->createInvitation($organizationId, 'invitee@example.com');
        $employee = Employee::mirror(AccountId::generate(), 'invitee@example.com');

        $this->invitations->method('findByToken')->with('a-token')->willReturn($invitation);
        $this->accountRegistrar
            ->expects($this->once())
            ->method('registerFromInvitation')
            ->with('invitee@example.com', 'NewPassword1!')
            ->willReturn($employee->accountId()->asString());
        $this->employees->method('findByAccountId')->willReturn($employee);
        $this->employees->expects($this->once())->method('save')->with($employee);
        $this->invitations->expects($this->once())->method('save')->with($invitation);

        // Act
        $result = ($this->handler)(new AcceptInvitationCommand('a-token', 'NewPassword1!'));

        // Assert
        Assert::assertSame('invitee@example.com', $result->email);
        Assert::assertTrue($employee->belongsTo($organizationId));
        Assert::assertSame(RoleEnum::USER, $employee->role());
        Assert::assertTrue($invitation->isAccepted());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throws_when_token_is_unknown(): void
    {
        // Arrange
        $this->invitations->method('findByToken')->willReturn(null);

        // Assert
        $this->expectException(InvitationNotFoundException::class);

        // Act
        ($this->handler)(new AcceptInvitationCommand('missing-token', 'NewPassword1!'));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throws_when_invitation_already_accepted(): void
    {
        // Arrange
        $invitation = $this->createInvitation(OrganizationId::generate(), 'invitee@example.com');
        $invitation->accept();
        $this->invitations->method('findByToken')->willReturn($invitation);
        $this->accountRegistrar->expects($this->never())->method('registerFromInvitation');

        // Assert
        $this->expectException(InvitationAlreadyAcceptedException::class);

        // Act
        ($this->handler)(new AcceptInvitationCommand('a-token', 'NewPassword1!'));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throws_when_invitation_expired(): void
    {
        // Arrange
        $invitation = $this->createInvitation(OrganizationId::generate(), 'invitee@example.com', new \DateTimeImmutable('-1 minute'));
        $this->invitations->method('findByToken')->willReturn($invitation);
        $this->accountRegistrar->expects($this->never())->method('registerFromInvitation');

        // Assert
        $this->expectException(InvitationExpiredException::class);

        // Act
        ($this->handler)(new AcceptInvitationCommand('a-token', 'NewPassword1!'));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->invitations = $this->createMock(InvitationRepositoryInterface::class);
        $this->employees = $this->createMock(EmployeeRepositoryInterface::class);
        $this->accountRegistrar = $this->createMock(InvitedAccountRegistrarInterface::class);

        $this->handler = new AcceptInvitationHandler($this->invitations, $this->employees, $this->accountRegistrar);
    }

    private function createInvitation(
        OrganizationId $organizationId,
        string $email,
        ?\DateTimeImmutable $expiresAt = null,
    ): Invitation {
        return Invitation::create(
            id: InvitationId::generate(),
            organizationId: $organizationId,
            email: $email,
            role: RoleEnum::USER,
            invitedByAccountId: AccountId::generate()->asString(),
            token: 'a-token',
            expiresAt: $expiresAt ?? new \DateTimeImmutable('+7 days'),
        );
    }
}
