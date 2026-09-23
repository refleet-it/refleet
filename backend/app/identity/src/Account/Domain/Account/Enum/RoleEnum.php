<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Enum;

enum RoleEnum: string
{
    public function toSymfonyRole(): string
    {
        return match ($this) {
            self::USER => 'ROLE_USER',
            self::ADMINISTRATOR => 'ROLE_ADMINISTRATOR',
        };
    }

    case USER = 'user';
    case ADMINISTRATOR = 'administrator';
}
