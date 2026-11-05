<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\ShiftTarget\Enum;

enum PipelineFixOutcomeEnum: string
{
    /** A commit was pushed, so a new pipeline is on its way. */
    case FIXED = 'fixed';
    case UNRELATED = 'unrelated';
    /** The merge request moved on or closed before the agent could act. */
    case SUPERSEDED = 'superseded';
    /** The agent looked and had nothing it could fix. Asking it again would get the same answer. */
    case NOT_FIXED = 'not_fixed';
    /** The job itself failed, say on a push the repository's rules rejected. It says nothing about the pipeline. */
    case JOB_FAILED = 'job_failed';
}
