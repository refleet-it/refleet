<?php

declare(strict_types=1);

namespace App\Runner\Runner\Domain\Runner\Enum;

enum RunnerStatusEnum: string
{
    case WORKING = 'working';
    case IDLE = 'idle';
    case OFFLINE = 'offline';
}
