<?php

declare(strict_types=1);

namespace App\Notification\Notification\Infrastructure\Bus\QualificationFinished;

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
