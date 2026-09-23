<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Bus\ShiftFinished;

/**
 * Published when a shift reaches a terminal state so Notification can tell the author.
 * Notification owns a structurally identical copy; the contexts share only the wire
 * contract, never an import.
 */
final readonly class ShiftFinishedMessage
{
    public function __construct(
        public string $accountId,
        public string $email,
        public string $shiftId,
        public string $title,
        public string $outcome,
        public ?string $cancelReason,
    ) {
    }
}
