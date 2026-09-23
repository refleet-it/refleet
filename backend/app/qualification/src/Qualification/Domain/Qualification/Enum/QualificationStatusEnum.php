<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Domain\Qualification\Enum;

/**
 * DRAFT -> RUNNING -> COMPLETED (via start(), once every target settles). No "review"
 * phase: nothing downstream is gated on this Qualification, so a COMPLETED
 * Qualification is simply a durable, reusable artifact — its targets stay overridable.
 */
enum QualificationStatusEnum: string
{
    case DRAFT = 'draft';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
