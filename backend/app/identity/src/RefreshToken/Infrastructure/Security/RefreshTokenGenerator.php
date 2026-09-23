<?php

declare(strict_types=1);

namespace App\Identity\RefreshToken\Infrastructure\Security;

/**
 * Only the hash is ever persisted - mirrors ApiKeyTokenGenerator so a database
 * leak cannot be replayed as a valid 7-day session for every account.
 */
final readonly class RefreshTokenGenerator
{
    private const int SECRET_BYTES = 64;

    public function generate(): GeneratedRefreshToken
    {
        $plainToken = \bin2hex(\random_bytes(self::SECRET_BYTES));

        return new GeneratedRefreshToken(
            plainToken: $plainToken,
            hashedToken: \hash('sha256', $plainToken),
        );
    }
}
