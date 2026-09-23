<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Command\ArchiveQualification;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ArchiveQualificationCommand implements CommandInterface
{
    public function __construct(
        public string $qualificationId,
        public string $organizationId,
    ) {
    }
}
