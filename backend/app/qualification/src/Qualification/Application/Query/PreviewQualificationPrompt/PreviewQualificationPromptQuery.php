<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\PreviewQualificationPrompt;

final readonly class PreviewQualificationPromptQuery
{
    public function __construct(
        public string $qualificationPrompt,
        public ?string $qualificationRules,
    ) {
    }
}
