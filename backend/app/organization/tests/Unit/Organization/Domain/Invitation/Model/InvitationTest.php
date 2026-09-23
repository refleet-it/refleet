<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Domain\Invitation\Model;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Invitation\Model\Invitation;
use App\Organization\Organization\Domain\Invitation\ValueObject\InvitationId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Invitation::class)]
final class InvitationTest extends TestCase
{
    #[Test]
    public function is_pending_when_not_accepted_and_not_expired(): void
    {
        $invitation = $this->createInvitation(new \DateTimeImmutable('+1 week'));

        Assert::assertTrue($invitation->isPending());
        Assert::assertFalse($invitation->isExpired());
        Assert::assertFalse($invitation->isAccepted());
    }

    #[Test]
    public function is_not_pending_once_expired(): void
    {
        $invitation = $this->createInvitation(new \DateTimeImmutable('-1 minute'));

        Assert::assertTrue($invitation->isExpired());
        Assert::assertFalse($invitation->isPending());
    }

    #[Test]
    public function is_not_pending_once_accepted(): void
    {
        $invitation = $this->createInvitation(new \DateTimeImmutable('+1 week'));

        $invitation->accept();

        Assert::assertTrue($invitation->isAccepted());
        Assert::assertFalse($invitation->isPending());
    }

    #[Test]
    public function is_not_pending_once_cancelled(): void
    {
        $invitation = $this->createInvitation(new \DateTimeImmutable('+1 week'));

        $invitation->cancel();

        Assert::assertTrue($invitation->isCancelled());
        Assert::assertFalse($invitation->isPending());
    }

    #[Test]
    public function lowercases_the_email_on_creation(): void
    {
        $invitation = Invitation::create(
            id: InvitationId::generate(),
            organizationId: OrganizationId::generate(),
            email: 'Invitee@Example.com',
            role: RoleEnum::USER,
            invitedByAccountId: '11111111-2222-3333-4444-555555555555',
            token: 'a-token',
            expiresAt: new \DateTimeImmutable('+1 week'),
        );

        Assert::assertSame('invitee@example.com', $invitation->email());
    }

    private function createInvitation(\DateTimeImmutable $expiresAt): Invitation
    {
        return Invitation::create(
            id: InvitationId::generate(),
            organizationId: OrganizationId::generate(),
            email: 'invitee@example.com',
            role: RoleEnum::USER,
            invitedByAccountId: '11111111-2222-3333-4444-555555555555',
            token: 'a-token',
            expiresAt: $expiresAt,
        );
    }
}
