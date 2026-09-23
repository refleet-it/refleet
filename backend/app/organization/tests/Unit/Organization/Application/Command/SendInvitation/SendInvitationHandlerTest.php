<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Application\Command\SendInvitation;

use App\Organization\Organization\Application\Command\SendInvitation\SendInvitationCommand;
use App\Organization\Organization\Application\Command\SendInvitation\SendInvitationHandler;
use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\AccountAlreadyBelongsToOrganizationException;
use App\Organization\Organization\Domain\Employee\Exception\NotOrganizationOwnerException;
use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Invitation\Exception\InvitationAlreadyPendingException;
use App\Organization\Organization\Domain\Invitation\Model\Invitation;
use App\Organization\Organization\Domain\Invitation\Repository\InvitationRepositoryInterface;
use App\Organization\Organization\Domain\Invitation\ValueObject\InvitationId;
use App\Organization\Organization\Domain\Organization\Model\Organization;
use App\Organization\Organization\Domain\Organization\Repository\OrganizationRepositoryInterface;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\Service\TemplateEmailSenderInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendInvitationHandler::class)]
final class SendInvitationHandlerTest extends TestCase
{
    private EmployeeRepositoryInterface&Stub $employees;

    private OrganizationRepositoryInterface&Stub $organizations;

    private InvitationRepositoryInterface&MockObject $invitations;

    private TemplateEmailSenderInterface&MockObject $emailSender;

    private SendInvitationHandler $handler;

    #[Test]
    public function owner_invites_a_new_email_and_an_email_is_sent(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization($organizationId, RoleEnum::OWNER);

        $organization = Organization::create($organizationId, 'Acme Inc', $owner->accountId());

        $this->employees->method('findByAccountId')->willReturn($owner);
        $this->employees->method('findByEmail')->willReturn(null);
        $this->invitations->method('findPendingByOrganizationIdAndEmail')->willReturn(null);
        $this->organizations->method('findById')->willReturn($organization);

        $this->invitations->expects($this->once())->method('save');
        $this->emailSender
            ->expects($this->once())
            ->method('sendWithTemplate')
            ->with(
                'invitee@example.com',
                $this->stringContains('Acme Inc'),
                'notifications/email/invitation.html.twig',
                $this->callback(static function (array $data): bool {
                    Assert::assertSame('Acme Inc', $data['organizationName']);
                    Assert::assertStringContainsString('/auth/accept-invitation?token=', (string) $data['acceptUrl']);

                    return true;
                })
            )
            ->willReturn(true);

        // Act
        $result = ($this->handler)(new SendInvitationCommand($owner->accountId()->asString(), 'invitee@example.com'));

        // Assert
        Assert::assertSame('invitee@example.com', $result->email);
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
        ($this->handler)(new SendInvitationCommand($member->accountId()->asString(), 'invitee@example.com'));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throws_when_target_already_belongs_to_an_organization(): void
    {
        // Arrange
        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization(OrganizationId::generate(), RoleEnum::OWNER);

        $target = Employee::mirror(AccountId::generate(), 'busy@example.com');
        $target->joinOrganization(OrganizationId::generate(), RoleEnum::USER);

        $this->employees->method('findByAccountId')->willReturn($owner);
        $this->employees->method('findByEmail')->willReturn($target);

        // Assert
        $this->expectException(AccountAlreadyBelongsToOrganizationException::class);

        // Act
        ($this->handler)(new SendInvitationCommand($owner->accountId()->asString(), 'busy@example.com'));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throws_when_an_invitation_is_already_pending(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization($organizationId, RoleEnum::OWNER);

        $this->employees->method('findByAccountId')->willReturn($owner);
        $this->employees->method('findByEmail')->willReturn(null);

        $pending = Invitation::create(
            id: InvitationId::generate(),
            organizationId: $organizationId,
            email: 'invitee@example.com',
            role: RoleEnum::USER,
            invitedByAccountId: $owner->accountId()->asString(),
            token: 'existing-token',
            expiresAt: new \DateTimeImmutable('+7 days'),
        );
        $this->invitations->method('findPendingByOrganizationIdAndEmail')->willReturn($pending);

        // Assert
        $this->expectException(InvitationAlreadyPendingException::class);

        // Act
        ($this->handler)(new SendInvitationCommand($owner->accountId()->asString(), 'invitee@example.com'));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->employees = $this->createStub(EmployeeRepositoryInterface::class);
        $this->organizations = $this->createStub(OrganizationRepositoryInterface::class);
        $this->invitations = $this->createMock(InvitationRepositoryInterface::class);
        $this->emailSender = $this->createMock(TemplateEmailSenderInterface::class);

        $this->handler = new SendInvitationHandler(
            $this->employees,
            $this->organizations,
            $this->invitations,
            $this->emailSender,
            'https://app.refleet.test',
        );
    }
}
