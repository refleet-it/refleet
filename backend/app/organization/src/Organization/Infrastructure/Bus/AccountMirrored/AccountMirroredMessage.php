<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Bus\AccountMirrored;

/**
 * Organization's own copy of the message Identity publishes when an account is created.
 * Both sides agree only on the wire contract, never on an import.
 */
final readonly class AccountMirroredMessage
{
    public function __construct(
        public string $accountId,
        public string $email,
    ) {
    }
}
