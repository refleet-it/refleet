<?php

declare(strict_types=1);

namespace App\Identity\RefreshToken\Domain\RefreshToken\Model;

use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\RefreshToken\Domain\RefreshToken\ValueObject\RefreshTokenId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens', schema: 'identity')]
class RefreshToken
{
    #[ORM\Column(type: 'boolean')]
    private bool $isRevoked = false;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID, unique: true)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'refreshTokens')]
        #[ORM\JoinColumn(name: 'account_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Account $account,
        #[ORM\Column(type: 'string', unique: true)]
        private string $token,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $expiresAt,
        #[ORM\Column(type: Types::GUID, nullable: true)]
        private ?string $impersonatorId = null,
    ) {
    }

    public static function create(
        RefreshTokenId $id,
        Account $account,
        string $token,
        \DateTimeImmutable $expiresAt,
        ?string $impersonatorId = null,
    ): self {
        return new self(
            id: $id->asString(),
            account: $account,
            token: $token,
            expiresAt: $expiresAt,
            impersonatorId: $impersonatorId,
        );
    }

    public function id(): RefreshTokenId
    {
        return RefreshTokenId::fromString($this->id);
    }

    public function account(): Account
    {
        return $this->account;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isRevoked(): bool
    {
        return $this->isRevoked;
    }

    public function revoke(): void
    {
        $this->isRevoked = true;
    }

    public function setRevoked(bool $isRevoked): void
    {
        $this->isRevoked = $isRevoked;
    }

    public function isValid(): bool
    {
        return !$this->isRevoked && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function impersonatorId(): ?AccountId
    {
        return null !== $this->impersonatorId ? AccountId::fromString($this->impersonatorId) : null;
    }
}
