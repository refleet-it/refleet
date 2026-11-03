<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Enum;

enum OAuthProvider: string
{
    public function isLocal(): bool
    {
        return self::LOCAL === $this;
    }

    public function isGoogle(): bool
    {
        return self::GOOGLE === $this;
    }

    public function isOidc(): bool
    {
        return self::OIDC === $this;
    }

    case LOCAL = 'local';
    case GOOGLE = 'google';
    // One value rather than one per issuer: an instance configures a single OIDC provider, and
    // which one it is belongs to its configuration, not to the accounts. Pointing an instance
    // at a different issuer is a migration either way, since the subjects do not carry over.
    case OIDC = 'oidc';
}
