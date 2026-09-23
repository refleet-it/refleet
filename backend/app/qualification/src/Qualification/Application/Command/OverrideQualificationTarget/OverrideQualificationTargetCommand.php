<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Command\OverrideQualificationTarget;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class OverrideQualificationTargetCommand implements CommandInterface
{
    public function __construct(
        public string $qualificationId,
        public string $organizationId,
        public string $targetId,
        public bool $qualified,
        public ?string $note = null,
    ) {
    }
}
