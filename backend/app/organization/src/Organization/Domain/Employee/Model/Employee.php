<?php

declare(strict_types=1);

namespace App\Organization\Organization\Domain\Employee\Model;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\AccountAlreadyBelongsToOrganizationException;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\Event\AggregateRoot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'employees', schema: 'organization')]
#[ORM\Index(name: 'idx_employees_organization_id', columns: ['organization_id'])]
#[ORM\UniqueConstraint(name: 'uniq_employees_email', columns: ['email'])]
class Employee extends AggregateRoot
{
    #[ORM\Id]
    #[ORM\Column(name: 'account_id', type: Types::GUID, unique: true)]
    private string $accountId;

    #[ORM\Column(type: Types::STRING)]
    private string $email;

    #[ORM\Column(name: 'organization_id', type: Types::GUID, nullable: true)]
    private ?string $organizationId = null;

    #[ORM\Column(type: Types::STRING, nullable: true, enumType: RoleEnum::class)]
    private ?RoleEnum $role = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'joined_organization_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $joinedOrganizationAt = null;

    private function __construct(AccountId $accountId, string $email)
    {
        $this->accountId = $accountId->asString();
        $this->email = \mb_strtolower($email);
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function mirror(AccountId $accountId, string $email): self
    {
        return new self($accountId, $email);
    }

    public function joinOrganization(OrganizationId $organizationId, RoleEnum $role): void
    {
        if ($this->isInOrganization()) {
            throw new AccountAlreadyBelongsToOrganizationException();
        }

        $this->organizationId = $organizationId->asString();
        $this->role = $role;
        $this->joinedOrganizationAt = new \DateTimeImmutable();
    }

    public function changeRole(RoleEnum $role): void
    {
        $this->role = $role;
    }

    public function leaveOrganization(): void
    {
        $this->organizationId = null;
        $this->role = null;
        $this->joinedOrganizationAt = null;
    }

    public function syncEmail(string $newEmail): bool
    {
        $normalizedEmail = \mb_strtolower($newEmail);

        if ($this->email === $normalizedEmail) {
            return false;
        }

        $this->email = $normalizedEmail;

        return true;
    }

    public function isInOrganization(): bool
    {
        return null !== $this->organizationId;
    }

    public function belongsTo(OrganizationId $organizationId): bool
    {
        return $this->organizationId === $organizationId->asString();
    }

    public function accountId(): AccountId
    {
        return AccountId::fromString($this->accountId);
    }

    public function email(): string
    {
        return $this->email;
    }

    public function organizationId(): ?OrganizationId
    {
        return null !== $this->organizationId ? OrganizationId::fromString($this->organizationId) : null;
    }

    public function role(): ?RoleEnum
    {
        return $this->role;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function joinedOrganizationAt(): ?\DateTimeImmutable
    {
        return $this->joinedOrganizationAt;
    }
}
