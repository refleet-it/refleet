<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\PreviewShiftChangePrompt;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'PreviewShiftChangePrompt',
    description: 'The change text and rules to render into the full prompt a change job would carry.'
)]
final readonly class PreviewShiftChangePromptRequest
{
    public function __construct(
        #[OA\Property(type: 'string', example: 'Bump acme/legacy-lib to ^3.0 in composer.json.')]
        public string $changePrompt = '',
        #[OA\Property(type: 'string', example: 'Commit subject: Conventional Commits, English, imperative.', nullable: true)]
        public ?string $changeRules = null,
    ) {
    }
}
