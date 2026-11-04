<?php

declare(strict_types=1);

namespace App\Runner\Runner\Domain\RunnerJob\Enum;

/**
 * Which module owns the job's outcome: QUALIFICATION jobs are enqueued by the
 * Qualification context and their result is recorded back there; CHANGE and PIPELINE_FIX
 * jobs are enqueued by the Shift context. This field alone disambiguates the owner — no
 * separate "owner kind" field is needed.
 */
enum RunnerJobKindEnum: string
{
    /**
     * What a runner gets when it does not say what it supports. That is every runner from
     * before PIPELINE_FIX existed, and those treat a kind they do not know as a change: they
     * would run the fix on the default branch and force-push over the merge request. So a
     * new kind is only ever handed to a runner that asks for it by name.
     *
     * @return list<self>
     */
    public static function claimedByDefault(): array
    {
        return [self::QUALIFICATION, self::CHANGE];
    }

    case QUALIFICATION = 'qualification';
    case CHANGE = 'change';
    case PIPELINE_FIX = 'pipeline_fix';
}
