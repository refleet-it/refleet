<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\PreviewShiftChangePrompt;

final readonly class PreviewShiftChangePromptQuery
{
    public function __construct(
        public string $changePrompt,
        public ?string $changeRules,
    ) {
    }
}
