<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Event;

use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Shared\Domain\Event\DomainEvent;

final readonly class AccountUpdated extends DomainEvent
{
    public function __construct(
        public AccountId $accountId,
        public string $email,
        public int $version,
    ) {
        parent::__construct($accountId);
    }
}
