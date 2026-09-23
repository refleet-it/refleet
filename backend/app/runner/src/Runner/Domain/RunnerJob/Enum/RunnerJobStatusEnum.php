<?php

declare(strict_types=1);

namespace App\Runner\Runner\Domain\RunnerJob\Enum;

/**
 * The REST claim&report integration has no heartbeat, so claim() IS the moment work
 * starts — there is no separate IN_PROGRESS state. CANCELLED is reached only when the
 * owning Qualification/Shift is cancelled while the job is still PENDING/CLAIMED.
 */
enum RunnerJobStatusEnum: string
{
    case PENDING = 'pending';
    case CLAIMED = 'claimed';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
