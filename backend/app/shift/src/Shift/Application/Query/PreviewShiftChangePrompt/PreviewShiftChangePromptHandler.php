<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\PreviewShiftChangePrompt;

use App\Shift\Shift\Domain\Shift\Service\ShiftJobPayloadFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Renders the exact text a change job would carry, through the same factory the job
 * payload uses — so what the user previews is what the agent gets, never a copy.
 */
#[AsMessageHandler]
final readonly class PreviewShiftChangePromptHandler
{
    public function __construct(
        private ShiftJobPayloadFactory $payloadFactory,
    ) {
    }

    public function __invoke(PreviewShiftChangePromptQuery $query): string
    {
        return $this->payloadFactory->buildAiChangePrompt($query->changePrompt, $query->changeRules);
    }
}
