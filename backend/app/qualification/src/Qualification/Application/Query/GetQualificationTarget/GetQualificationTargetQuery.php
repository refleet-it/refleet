<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\GetQualificationTarget;

final readonly class GetQualificationTargetQuery
{
    public function __construct(
        public string $targetId,
        public string $qualificationId,
        public string $organizationId,
    ) {
    }
}
