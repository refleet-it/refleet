<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\ShiftTarget\Enum;

enum ShiftTargetStatusEnum: string
{
    /**
     * Targets currently "in flight" (including the merge-request wait). Used to decide
     * when Shift::complete() should fire.
     *
     * @return self[]
     */
    public static function inFlightStatuses(): array
    {
        return [self::CHANGE_IN_PROGRESS, self::MERGE_REQUEST_OPEN];
    }

    /**
     * Targets whose last run has settled and can be sent through the agent again — after a
     * failure, to redo an open or rejected merge request with a revised prompt, or because
     * the agent found nothing to change last time.
     *
     * @return self[]
     */
    public static function rerunnableStatuses(): array
    {
        return [self::CHANGE_FAILED, self::MERGE_REQUEST_OPEN, self::MERGE_REQUEST_CLOSED, self::NO_CHANGES];
    }

    public function isTerminal(): bool
    {
        return !\in_array($this, [self::PENDING_CHANGE, self::CHANGE_IN_PROGRESS, self::MERGE_REQUEST_OPEN], true);
    }

    case PENDING_CHANGE = 'pending_change';
    case CHANGE_IN_PROGRESS = 'change_in_progress';
    case CHANGE_FAILED = 'change_failed';
    case NO_CHANGES = 'no_changes';
    case MERGE_REQUEST_OPEN = 'merge_request_open';
    case COMPLETED = 'completed';
    case MERGE_REQUEST_CLOSED = 'merge_request_closed';
    case CANCELLED = 'cancelled';
}
