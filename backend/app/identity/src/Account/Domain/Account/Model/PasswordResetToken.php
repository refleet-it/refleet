<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Model;

use App\Shared\Domain\ValueObject\Id;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'password_reset_tokens', schema: 'identity')]
class PasswordResetToken
{
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isUsed = false;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID, unique: true)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'passwordResetTokens')]
        #[ORM\JoinColumn(name: 'account_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Account $account,
        #[ORM\Column(type: Types::STRING, unique: true)]
        private string $token,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $expiresAt,
    ) {
    }

    public static function create(
        Id $id,
        Account $account,
        string $token,
        \DateTimeImmutable $expiresAt,
    ): self {
        return new self(
            id: $id->asString(),
            account: $account,
            token: $token,
            expiresAt: $expiresAt
        );
    }

    public function id(): Id
    {
        return Id::fromString($this->id);
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

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isUsed(): bool
    {
        return $this->isUsed;
    }

    public function markAsUsed(): void
    {
        $this->isUsed = true;
    }
}
