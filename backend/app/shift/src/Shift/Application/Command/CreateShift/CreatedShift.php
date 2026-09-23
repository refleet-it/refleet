<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\CreateShift;

final readonly class CreatedShift
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public string $status,
        public ?string $qualificationId,
        public int $targetCount,
        public string $createdAt,
    ) {
    }
}
