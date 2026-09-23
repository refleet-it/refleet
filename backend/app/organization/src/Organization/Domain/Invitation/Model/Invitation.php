<?php

declare(strict_types=1);

namespace App\Organization\Organization\Domain\Invitation\Model;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Invitation\ValueObject\InvitationId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invitations', schema: 'organization')]
#[ORM\Index(name: 'idx_invitations_organization_id', columns: ['organization_id'])]
#[ORM\UniqueConstraint(name: 'uniq_invitations_token', columns: ['token'])]
class Invitation
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(name: 'cancelled_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID, unique: true)]
        private string $id,
        #[ORM\Column(name: 'organization_id', type: Types::GUID)]
        private string $organizationId,
        #[ORM\Column(type: Types::STRING)]
        private string $email,
        #[ORM\Column(type: Types::STRING, enumType: RoleEnum::class)]
        private RoleEnum $role,
        #[ORM\Column(name: 'invited_by_account_id', type: Types::GUID)]
        private string $invitedByAccountId,
        #[ORM\Column(type: Types::STRING, unique: true)]
        private string $token,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $expiresAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        InvitationId $id,
        OrganizationId $organizationId,
        string $email,
        RoleEnum $role,
        string $invitedByAccountId,
        string $token,
        \DateTimeImmutable $expiresAt,
    ): self {
        return new self(
            id: $id->asString(),
            organizationId: $organizationId->asString(),
            email: \mb_strtolower($email),
            role: $role,
            invitedByAccountId: $invitedByAccountId,
            token: $token,
            expiresAt: $expiresAt,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function id(): InvitationId
    {
        return InvitationId::fromString($this->id);
    }

    public function organizationId(): OrganizationId
    {
        return OrganizationId::fromString($this->organizationId);
    }

    public function email(): string
    {
        return $this->email;
    }

    public function role(): RoleEnum
    {
        return $this->role;
    }

    public function invitedByAccountId(): string
    {
        return $this->invitedByAccountId;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isAccepted(): bool
    {
        return null !== $this->acceptedAt;
    }

    public function isCancelled(): bool
    {
        return null !== $this->cancelledAt;
    }

    public function isPending(): bool
    {
        return !$this->isAccepted() && !$this->isCancelled() && !$this->isExpired();
    }

    public function accept(): void
    {
        $this->acceptedAt = new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        $this->cancelledAt = new \DateTimeImmutable();
    }
}
