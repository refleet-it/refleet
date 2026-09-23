<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Domain\QualificationTarget\Enum;

enum QualificationTargetStatusEnum: string
{
    /**
     * Targets still to be picked up or currently being checked. Used to decide when
     * Qualification::complete() should fire.
     *
     * @return self[]
     */
    public static function nonTerminalStatuses(): array
    {
        return [self::PENDING, self::IN_PROGRESS];
    }

    public function isTerminal(): bool
    {
        return !\in_array($this, self::nonTerminalStatuses(), true);
    }

    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case QUALIFIED = 'qualified';
    case NOT_QUALIFIED = 'not_qualified';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
