<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\PreviewQualificationPrompt;

use App\Qualification\Qualification\Domain\Qualification\Service\QualificationJobPayloadFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Renders the exact text a qualification job would carry, through the same factory the
 * job payload uses — so what the user previews is what the agent gets, never a copy.
 */
#[AsMessageHandler]
final readonly class PreviewQualificationPromptHandler
{
    public function __construct(
        private QualificationJobPayloadFactory $payloadFactory,
    ) {
    }

    public function __invoke(PreviewQualificationPromptQuery $query): string
    {
        return $this->payloadFactory->buildAiQualificationPrompt($query->qualificationPrompt, $query->qualificationRules);
    }
}
