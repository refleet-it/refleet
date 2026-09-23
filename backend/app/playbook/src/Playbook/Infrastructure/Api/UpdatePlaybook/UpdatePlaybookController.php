<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Infrastructure\Api\UpdatePlaybook;

use App\Playbook\Playbook\Application\Command\UpdatePlaybook\UpdatePlaybookCommand;
use App\Playbook\Playbook\Application\Query\GetPlaybook\GetPlaybookQuery;
use App\Playbook\Playbook\Application\Query\ListPlaybooks\PlaybookView;
use App\Playbook\Playbook\Infrastructure\Api\CreatePlaybook\PlaybookPayloadRequest;
use App\Playbook\Playbook\Infrastructure\Api\PlaybookIdRequirement;
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

#[Route('/playbooks/{id}', name: 'playbook_update', requirements: ['id' => PlaybookIdRequirement::PATTERN], methods: ['PUT'])]
#[OA\Put(
    description: "Replace one of the organization's playbooks. Built-in playbooks are read-only.",
    summary: 'Update Playbook',
    tags: ['Playbook'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Playbook ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Playbook updated'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Playbook not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, or the playbook is built-in'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Invalid playbook'),
    ]
)]
final readonly class UpdatePlaybookController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        string $id,
        #[MapRequestPayload]
        PlaybookPayloadRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $this->bus->dispatch(new UpdatePlaybookCommand(
            playbookId: $id,
            organizationId: $organization->id,
            name: $payload->name,
            description: $payload->description,
            kind: $payload->kind,
            appliesTo: $payload->appliesTo,
            body: $payload->body,
            default: $payload->default,
            parameters: $payload->parameters,
            engine: $payload->engine,
            model: $payload->model,
        ));

        $handledStamp = $this->bus->dispatch(new GetPlaybookQuery(
            playbookId: $id,
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var PlaybookView $view */
        $view = $handledStamp?->getResult();

        return new JsonResponse($view->toArray(), Response::HTTP_OK);
    }
}
