<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Event;

use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Shared\Domain\Event\DomainEventInterface;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('sync')]
final readonly class PasswordChanged implements DomainEventInterface
{
    public function __construct(
        private AccountId $accountId,
        private string $email,
    ) {
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
    }

    public function email(): string
    {
        return $this->email;
    }
}
