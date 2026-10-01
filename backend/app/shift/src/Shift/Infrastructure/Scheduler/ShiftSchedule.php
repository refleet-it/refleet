<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Scheduler;

use App\Shift\Shift\Application\Command\PollOpenMergeRequests\PollOpenMergeRequestsCommand;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Drives the shift context's recurring work. Consumed by worker-shift, which reads the
 * generated `scheduler_shift` transport alongside `to_shift`.
 *
 * Deliberately neither stateful nor locked: one worker container runs per context, so
 * there is no second scheduler to race with, and catching up on triggers missed while the
 * worker was down would only re-run a poll that the next tick does anyway.
 */
#[AsSchedule('shift')]
final class ShiftSchedule implements ScheduleProviderInterface
{
    /**
     * A merge request waits on a human for hours or days, so minutes of lag cost nothing
     * — and the interval is what bounds how often the fleet talks to GitLab.
     */
    private const string POLL_INTERVAL = '5 minutes';

    private ?Schedule $schedule = null;

    #[\Override]
    public function getSchedule(): Schedule
    {
        return $this->schedule ??= (new Schedule())
            ->add(RecurringMessage::every(self::POLL_INTERVAL, new PollOpenMergeRequestsCommand()));
    }
}
