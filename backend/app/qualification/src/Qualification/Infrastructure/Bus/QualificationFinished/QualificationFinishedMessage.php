<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Bus\QualificationFinished;

/**
 * Published when a qualification reaches a terminal state so Notification can tell the
 * author. Notification owns a structurally identical copy; the contexts share only the
 * wire contract, never an import.
 */
final readonly class QualificationFinishedMessage
{
    public function __construct(
        public string $accountId,
        public string $email,
        public string $qualificationId,
        public string $title,
        public string $outcome,
        public ?string $cancelReason,
    ) {
    }
}
