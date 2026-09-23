<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\ClaimCliAuthorization;

use App\Identity\Account\Application\Command\CreateApiKey\CreatedApiKey;

/**
 * What one poll from the CLI learns. `apiKey` and `accountEmail` are set only for
 * `approved`, and that answer is given once — the authorization is gone afterwards.
 */
final readonly class ClaimedCliAuthorization
{
    public const string PENDING = 'pending';

    public const string APPROVED = 'approved';

    public const string DENIED = 'denied';

    public const string EXPIRED = 'expired';

    public function __construct(
        public string $status,
        public ?CreatedApiKey $apiKey = null,
        public ?string $accountEmail = null,
    ) {
    }
}
