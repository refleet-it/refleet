<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Event;

use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Shared\Domain\Event\DomainEvent;

final readonly class PasswordResetEmailRequested extends DomainEvent
{
    public function __construct(
        private AccountId $accountId,
        private string $email,
        private string $resetToken,
        private \DateTimeImmutable $occurredAt,
    ) {
        parent::__construct($accountId);
    }

    public static function fromPasswordResetRequested(
        AccountId $accountId,
        string $email,
        string $resetToken,
        \DateTimeImmutable $occurredAt,
    ): self {
        return new self(
            accountId: $accountId,
            email: $email,
            resetToken: $resetToken,
            occurredAt: $occurredAt,
        );
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function resetToken(): string
    {
        return $this->resetToken;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
