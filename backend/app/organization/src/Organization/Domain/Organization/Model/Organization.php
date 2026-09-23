<?php

declare(strict_types=1);

namespace App\Organization\Organization\Domain\Organization\Model;

use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\Event\OrganizationCreated;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\Event\AggregateRoot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'organizations', schema: 'organization')]
class Organization extends AggregateRoot
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(name: 'owner_account_id', type: Types::GUID)]
    private string $ownerAccountId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    private function __construct(OrganizationId $id, #[ORM\Column(type: Types::STRING, length: 100)]
        private string $name, AccountId $ownerAccountId)
    {
        $this->id = $id->asString();
        $this->ownerAccountId = $ownerAccountId->asString();
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function create(OrganizationId $id, string $name, AccountId $ownerAccountId): self
    {
        $organization = new self($id, $name, $ownerAccountId);
        $organization->recordThat(new OrganizationCreated($id, $name));

        return $organization;
    }

    public function id(): OrganizationId
    {
        return OrganizationId::fromString($this->id);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function ownerAccountId(): AccountId
    {
        return AccountId::fromString($this->ownerAccountId);
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
