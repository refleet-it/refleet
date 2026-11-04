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
    case NOT_FIXED = 'not_fixed';
}
