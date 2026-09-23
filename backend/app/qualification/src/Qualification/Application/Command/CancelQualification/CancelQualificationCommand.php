<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Command\CancelQualification;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class CancelQualificationCommand implements CommandInterface
{
    public function __construct(
        public string $qualificationId,
        public string $organizationId,
        public ?string $reason = null,
    ) {
    }
}
