<?php

declare(strict_types=1);

namespace App\Notification\Notification\Infrastructure\Bus\ShiftFinished;

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
