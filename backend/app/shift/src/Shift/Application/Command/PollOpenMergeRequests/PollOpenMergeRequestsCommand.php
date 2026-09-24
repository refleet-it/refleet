<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\PollOpenMergeRequests;

use App\Shared\Application\Command\Sync\CommandInterface;

/**
 * Carries nothing: the poller works out for itself which merge requests are due a check.
 * Dispatched on a schedule by App\Shift\Shift\Infrastructure\Scheduler\ShiftSchedule.
 */
final readonly class PollOpenMergeRequestsCommand implements CommandInterface
{
}
