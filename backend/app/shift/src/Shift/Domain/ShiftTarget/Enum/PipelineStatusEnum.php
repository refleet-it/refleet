<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\ShiftTarget\Enum;

/**
 * What Refleet knows about the pipeline on a target's open merge request. A sub-lifecycle
 * of MERGE_REQUEST_OPEN rather than more target statuses, so merged/closed detection,
 * re-runs and shift completion keep working however this one is going.
 */
enum PipelineStatusEnum: string
{
    case NOT_WATCHED = 'not_watched';
    case WAITING = 'waiting';
    case PASSED = 'passed';
    case FIXING = 'fixing';
    /** The failure was not caused by the change; left for a person, with a note on the MR. */
    case UNRELATED = 'unrelated';
    /** The fix attempts ran out, or an attempt could not produce a fix. */
    case GAVE_UP = 'gave_up';
    /** The pipeline finished without an answer: cancelled, skipped or waiting on a manual job. */
    case INCONCLUSIVE = 'inconclusive';
    case NO_PIPELINE = 'no_pipeline';
    /** Someone else moved the merge request's branch, so Refleet stopped touching it. */
    case STOOD_DOWN = 'stood_down';
}
