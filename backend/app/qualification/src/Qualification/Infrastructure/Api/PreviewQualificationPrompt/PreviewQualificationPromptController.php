<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\PreviewQualificationPrompt;

use App\Qualification\Qualification\Application\Query\PreviewQualificationPrompt\PreviewQualificationPromptQuery;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/qualifications/prompt-preview', name: 'qualification_preview_prompt', methods: ['POST'])]
#[OA\Post(
    description: 'Render the complete prompt a qualification job would send to the agent for the given criteria and rules, including the fixed framing and answer contract Refleet adds around them. Nothing is stored.',
    summary: 'Preview Qualification Prompt',
    tags: ['Qualification'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'The full prompt'),
    ]
)]
final readonly class PreviewQualificationPromptController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        PreviewQualificationPromptRequest $payload,
    ): JsonResponse {
        $handledStamp = $this->bus->dispatch(new PreviewQualificationPromptQuery(
            qualificationPrompt: $payload->qualificationPrompt,
            qualificationRules: $payload->qualificationRules,
        ))->last(HandledStamp::class);

        /** @var string $prompt */
        $prompt = $handledStamp?->getResult();

        return new JsonResponse(['prompt' => $prompt], Response::HTTP_OK);
    }
}
