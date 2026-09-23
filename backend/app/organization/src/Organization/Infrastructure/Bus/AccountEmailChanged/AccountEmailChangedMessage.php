<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Bus\AccountEmailChanged;

/**
 * Organization's own copy of the message Identity publishes when an account's email
 * changes. Both sides agree only on the wire contract, never on an import.
 */
final readonly class AccountEmailChangedMessage
{
    public function __construct(
        public string $accountId,
        public string $email,
    ) {
    }
}
