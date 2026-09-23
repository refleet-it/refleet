<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\ApiKey\Model;

use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\ApiKey\ValueObject\ApiKeyId;
use App\Shared\Domain\Event\AggregateRoot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'api_keys', schema: 'identity')]
#[ORM\Index(name: 'idx_api_keys_account_id', columns: ['account_id'])]
#[ORM\UniqueConstraint(name: 'uniq_api_keys_hashed_secret', columns: ['hashed_secret'])]
class ApiKey extends AggregateRoot
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    private function __construct(
        ApiKeyId $id,
        #[ORM\ManyToOne(targetEntity: Account::class)]
        #[ORM\JoinColumn(name: 'account_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Account $account,
        #[ORM\Column(type: Types::STRING, length: 100)]
        private string $name,
        #[ORM\Column(type: Types::STRING, length: 16)]
        private string $keyPrefix,
        // @phpstan-ignore property.onlyWritten (never read in PHP on purpose: the hash is looked up by column in ApiKeyRepositoryInterface::findByHashedSecret and must not leak through an accessor)
        #[ORM\Column(type: Types::STRING, length: 64)]
        private string $hashedSecret,
    ) {
        $this->id = $id->asString();
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function create(ApiKeyId $id, Account $account, string $name, string $keyPrefix, string $hashedSecret): self
    {
        return new self($id, $account, $name, $keyPrefix, $hashedSecret);
    }

    public function id(): ApiKeyId
    {
        return ApiKeyId::fromString($this->id);
    }

    public function accountId(): string
    {
        return $this->account->id()->asString();
    }

    public function account(): Account
    {
        return $this->account;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function keyPrefix(): string
    {
        return $this->keyPrefix;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function revokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function revoke(): void
    {
        if ($this->isRevoked()) {
            return;
        }

        $this->revokedAt = new \DateTimeImmutable();
    }

    public function touchLastUsed(): void
    {
        $this->lastUsedAt = new \DateTimeImmutable();
    }
}
