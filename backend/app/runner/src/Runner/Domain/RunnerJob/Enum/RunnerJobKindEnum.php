<?php

declare(strict_types=1);

namespace App\Runner\Runner\Domain\RunnerJob\Enum;

/**
 * Which module owns the job's outcome: QUALIFICATION jobs are enqueued by the
 * Qualification context and their result is recorded back there; CHANGE jobs are
 * enqueued by the Shift context. This field alone disambiguates the owner — no
 * separate "owner kind" field is needed.
 */
enum RunnerJobKindEnum: string
{
    case QUALIFICATION = 'qualification';
    case CHANGE = 'change';
}
