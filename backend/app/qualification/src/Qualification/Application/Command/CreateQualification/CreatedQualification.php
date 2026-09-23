<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Command\CreateQualification;

final readonly class CreatedQualification
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public string $status,
        public string $qualificationMode,
        public int $targetCount,
        public string $createdAt,
    ) {
    }
}
