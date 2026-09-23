<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Infrastructure\Api\ComposePrompt;

use App\Playbook\Playbook\Application\Query\ComposePrompt\ComposePromptQuery;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\ComposedPrompt;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\ValueObject\PromptSource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/playbooks/compose', name: 'playbook_compose', methods: ['POST'])]
#[OA\Post(
    description: 'Turn a selection of playbooks into the text a shift or qualification starts from: the rules concatenated under their headings, the task rendered with its parameters, plus the task\'s preferred engine/model and the list of sources. Nothing is stored — the caller pastes the result into the editable prompt fields.',
    summary: 'Compose Prompt From Playbooks',
    tags: ['Playbook'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Composed text'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'A selected playbook does not exist'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'A required parameter is missing, or a playbook does not fit the requested usage'),
    ]
)]
final readonly class ComposePromptController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        ComposePromptRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $handledStamp = $this->bus->dispatch(new ComposePromptQuery(
            organizationId: $organization->id,
            appliesTo: $payload->appliesTo,
            ruleIds: $payload->ruleIds,
            taskId: $payload->taskId,
            parameters: $payload->parameters,
        ))->last(HandledStamp::class);

        /** @var ComposedPrompt $composed */
        $composed = $handledStamp?->getResult();

        return new JsonResponse([
            'rules' => $composed->rules(),
            'prompt' => $composed->prompt(),
            'engine' => $composed->engine()?->value,
            'model' => $composed->model(),
            'sources' => \array_map(static fn (PromptSource $source): array => $source->toArray(), $composed->sources()),
        ], Response::HTTP_OK);
    }
}
