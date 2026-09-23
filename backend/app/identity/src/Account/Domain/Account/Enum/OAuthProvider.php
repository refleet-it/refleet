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

    case LOCAL = 'local';
    case GOOGLE = 'google';
}
