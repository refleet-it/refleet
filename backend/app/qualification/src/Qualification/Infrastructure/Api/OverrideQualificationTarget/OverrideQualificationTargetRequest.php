<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\OverrideQualificationTarget;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'OverrideQualificationTarget',
    description: "Payload used to manually set (or flip) a single target's qualification decision, superseding the regex/AI result."
)]
final readonly class OverrideQualificationTargetRequest
{
    public function __construct(
        #[OA\Property(type: 'boolean', example: true)]
        public bool $qualified,
        #[Assert\Length(max: 1000)]
        #[OA\Property(type: 'string', example: 'Confirmed manually: this repo still depends on acme/legacy-lib via a transitive package.')]
        public ?string $note = null,
    ) {
    }
}
