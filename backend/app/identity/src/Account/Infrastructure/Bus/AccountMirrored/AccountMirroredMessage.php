<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Bus\AccountMirrored;

/**
 * Announces a newly created account so contexts that keep their own view of people can
 * mirror it. Both sides keep their own copy of this class and agree only on the wire
 * contract.
 */
final readonly class AccountMirroredMessage
{
    public function __construct(
        public string $accountId,
        public string $email,
    ) {
    }
}
