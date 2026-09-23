<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Security;

use App\Shared\Infrastructure\Security\ApiKeyAuthenticator;

/**
 * Long-lived, machine-to-machine API keys - distinct from the short-lived (1h)
 * JWTs issued at login. Format mirrors GitHub-style PATs: a readable prefix
 * (safe to display in the UI so a key can be identified without the secret)
 * followed by a high-entropy random secret.
 */
final readonly class ApiKeyTokenGenerator
{
    public const string PREFIX = ApiKeyAuthenticator::PREFIX;

    private const int SECRET_BYTES = 24;

    private const int DISPLAY_PREFIX_LENGTH = 12;

    public function generate(): GeneratedApiKeyToken
    {
        $plainToken = self::PREFIX.\bin2hex(\random_bytes(self::SECRET_BYTES));

        return new GeneratedApiKeyToken(
            plainToken: $plainToken,
            prefix: \substr($plainToken, 0, self::DISPLAY_PREFIX_LENGTH),
            hashedSecret: \hash('sha256', $plainToken),
        );
    }
}
