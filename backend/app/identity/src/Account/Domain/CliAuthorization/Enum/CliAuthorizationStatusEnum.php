<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\CliAuthorization\Enum;

enum CliAuthorizationStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case DENIED = 'denied';
}
