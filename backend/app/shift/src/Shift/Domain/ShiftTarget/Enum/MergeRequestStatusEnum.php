<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\ShiftTarget\Enum;

enum MergeRequestStatusEnum: string
{
    case NONE = 'none';
    case OPEN = 'open';
    case MERGED = 'merged';
    case CLOSED = 'closed';
}
