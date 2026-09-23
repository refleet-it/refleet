<?php

declare(strict_types=1);

namespace App\Organization\Organization\Domain\Employee\Enum;

enum RoleEnum: string
{
    case OWNER = 'owner';
    case USER = 'user';
}
