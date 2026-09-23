<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\GetQualification;

final readonly class GetQualificationQuery
{
    public function __construct(
        public string $qualificationId,
        public string $organizationId,
    ) {
    }
}
