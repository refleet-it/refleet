<?php

declare(strict_types=1);

namespace App\Project\Project\Infrastructure\Api\RegisterProject;

use App\Project\Project\Application\Command\RegisterProject\RegisteredProject;
use App\Project\Project\Application\Command\RegisterProject\RegisterProjectCommand;
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

#[Route('/projects', name: 'project_register', methods: ['POST'])]
#[OA\Post(
    description: "Register a GitLab project as part of the current account's organization fleet, or re-sync it if it was already registered (upsert by externalId).",
    summary: 'Register Project',
    tags: ['Project'],
    responses: [
        new OA\Response(response: Response::HTTP_CREATED, description: 'Project registered'),
        new OA\Response(response: Response::HTTP_OK, description: 'Existing project re-synced'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation errors'),
    ]
)]
final readonly class RegisterProjectController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        RegisterProjectRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $handledStamp = $this->bus->dispatch(new RegisterProjectCommand(
            organizationId: $organization->id,
            externalId: $payload->externalId,
            name: $payload->name,
            path: $payload->path,
            webUrl: $payload->webUrl,
            defaultBranch: $payload->defaultBranch,
            description: $payload->description,
        ))->last(HandledStamp::class);

        /** @var RegisteredProject $registered */
        $registered = $handledStamp?->getResult();

        return new JsonResponse([
            'id' => $registered->id,
            'name' => $registered->name,
            'externalId' => $registered->externalId,
            'path' => $registered->path,
            'webUrl' => $registered->webUrl,
            'defaultBranch' => $registered->defaultBranch,
            'description' => $registered->description,
            'createdAt' => $registered->createdAt,
            'lastSyncedAt' => $registered->lastSyncedAt,
        ], $registered->wasCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
