<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Infrastructure\Api\CreatePlaybook;

use App\Playbook\Playbook\Application\Command\CreatePlaybook\CreatedPlaybook;
use App\Playbook\Playbook\Application\Command\CreatePlaybook\CreatePlaybookCommand;
use App\Playbook\Playbook\Application\Query\GetPlaybook\GetPlaybookQuery;
use App\Playbook\Playbook\Application\Query\ListPlaybooks\PlaybookView;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/playbooks', name: 'playbook_create', methods: ['POST'])]
#[OA\Post(
    description: 'Create a playbook for the organization.',
    summary: 'Create Playbook',
    tags: ['Playbook'],
    responses: [
        new OA\Response(response: Response::HTTP_CREATED, description: 'Playbook created'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Invalid playbook'),
    ]
)]
final readonly class CreatePlaybookController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        PlaybookPayloadRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $handledStamp = $this->bus->dispatch(new CreatePlaybookCommand(
            organizationId: $organization->id,
            createdByAccountId: $user->getUserId()->asString(),
            name: $payload->name,
            description: $payload->description,
            kind: $payload->kind,
            appliesTo: $payload->appliesTo,
            body: $payload->body,
            default: $payload->default,
            parameters: $payload->parameters,
            engine: $payload->engine,
            model: $payload->model,
        ))->last(HandledStamp::class);

        /** @var CreatedPlaybook $created */
        $created = $handledStamp?->getResult();

        $handledStamp = $this->bus->dispatch(new GetPlaybookQuery(
            playbookId: $created->id,
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var PlaybookView $view */
        $view = $handledStamp?->getResult();

        return new JsonResponse($view->toArray(), Response::HTTP_CREATED);
    }
}
