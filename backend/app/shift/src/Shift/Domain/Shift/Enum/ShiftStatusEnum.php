<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\Shift\Enum;

enum ShiftStatusEnum: string
{
    case DRAFT = 'draft';
    case APPLYING_CHANGE = 'applying_change';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
