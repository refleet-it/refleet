<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\PreviewShiftChangePrompt;

use App\Shift\Shift\Application\Query\PreviewShiftChangePrompt\PreviewShiftChangePromptQuery;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/shifts/prompt-preview', name: 'shift_preview_change_prompt', methods: ['POST'])]
#[OA\Post(
    description: 'Render the complete prompt a change job would send to the agent for the given change text and rules, including the fixed framing and answer contract Refleet adds around them. Nothing is stored.',
    summary: 'Preview Shift Change Prompt',
    tags: ['Shift'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'The full prompt'),
    ]
)]
final readonly class PreviewShiftChangePromptController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        PreviewShiftChangePromptRequest $payload,
    ): JsonResponse {
        $handledStamp = $this->bus->dispatch(new PreviewShiftChangePromptQuery(
            changePrompt: $payload->changePrompt,
            changeRules: $payload->changeRules,
        ))->last(HandledStamp::class);

        /** @var string $prompt */
        $prompt = $handledStamp?->getResult();

        return new JsonResponse(['prompt' => $prompt], Response::HTTP_OK);
    }
}
