<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Event;

use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Shared\Domain\Event\DomainEvent;

final readonly class EmailVerificationEmailRequested extends DomainEvent
{
    public function __construct(
        private AccountId $accountId,
        private string $email,
        private string $verificationToken,
        private \DateTimeImmutable $occurredAt,
    ) {
        parent::__construct($accountId);
    }

    public static function fromEmailVerificationRequested(
        AccountId $accountId,
        string $email,
        string $verificationToken,
        \DateTimeImmutable $occurredAt,
    ): self {
        return new self(
            accountId: $accountId,
            email: $email,
            verificationToken: $verificationToken,
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

    public function verificationToken(): string
    {
        return $this->verificationToken;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
