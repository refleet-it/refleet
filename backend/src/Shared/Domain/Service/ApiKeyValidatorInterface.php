<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

use App\Shared\Domain\ValueObject\ValidatedApiKey;

/**
 * Resolves a plaintext API key to its owning account, without depending on the Identity
 * context that stores keys. Implementations also own the revocable-credential bookkeeping
 * (touching last-used, rejecting revoked keys or inactive accounts) — unlike JWTs, API keys
 * cannot be made self-contained, since revocation must take effect immediately.
 */
interface ApiKeyValidatorInterface
{
    public function validate(string $plainToken): ?ValidatedApiKey;
}
