<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\PreviewQualificationPrompt;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'PreviewQualificationPrompt',
    description: 'The criteria text and rules to render into the full prompt a qualification job would carry.'
)]
final readonly class PreviewQualificationPromptRequest
{
    public function __construct(
        #[OA\Property(type: 'string', example: 'Does this repository depend on acme/legacy-lib?')]
        public string $qualificationPrompt = '',
        #[OA\Property(type: 'string', example: 'Base the score only on files you actually inspected.', nullable: true)]
        public ?string $qualificationRules = null,
    ) {
    }
}
