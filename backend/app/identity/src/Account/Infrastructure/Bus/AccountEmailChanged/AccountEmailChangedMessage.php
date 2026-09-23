<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Bus\AccountEmailChanged;

/**
 * Announces an account's new email address so contexts mirroring that account can follow.
 * Both sides keep their own copy of this class and agree only on the wire contract.
 */
final readonly class AccountEmailChangedMessage
{
    public function __construct(
        public string $accountId,
        public string $email,
    ) {
    }
}
