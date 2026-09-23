<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

enum Status: string
{
    case ACTIVE = 'active';
    case DELETED = 'deleted';
}
