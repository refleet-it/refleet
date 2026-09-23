<?php

declare(strict_types=1);

namespace App\Shared\Domain\Enum;

/**
 * Shared by Qualification's criteria, Shift's change criteria, and RunnerJob.mode —
 * a runner job always executes in the same mode as the criteria that spawned it.
 *
 * AI is the only mode left: static regex rewrites (and the OpenRewrite/Rector engines
 * before them) were dropped, so every job is a prompt run by an agent. The value stays
 * on the wire and in the database because runners filter claims on it and a future mode
 * would slot in here rather than rebuild the plumbing.
 *
 * Manually selecting projects (bypassing qualification entirely) is not represented
 * here as a mode — it is a way of creating a Shift (see CreateShiftCommand), not a
 * criteria mode, since it never spawns a runner job.
 */
enum CriteriaModeEnum: string
{
    case AI = 'ai';
}
