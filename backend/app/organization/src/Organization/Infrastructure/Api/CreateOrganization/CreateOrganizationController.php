<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\CreateOrganization;

use App\Organization\Organization\Application\Command\CreateOrganization\CreatedOrganization;
use App\Organization\Organization\Application\Command\CreateOrganization\CreateOrganizationCommand;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/organizations', name: 'organization_create', methods: ['POST'])]
#[OA\Post(
    description: 'Create a new organization. The current account becomes its owner. An account can belong to only one organization.',
    summary: 'Create Organization',
    tags: ['Organization'],
    responses: [
        new OA\Response(response: Response::HTTP_CREATED, description: 'Organization created'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account already belongs to an organization'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation errors'),
    ]
)]
final readonly class CreateOrganizationController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        CreateOrganizationRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $handledStamp = $this->bus->dispatch(new CreateOrganizationCommand(
            accountId: $user->getUserId()->asString(),
            email: $user->getUserIdentifier(),
            name: $payload->name,
        ))->last(HandledStamp::class);

        /** @var CreatedOrganization $created */
        $created = $handledStamp?->getResult();

        return new JsonResponse([
            'id' => $created->id,
            'name' => $created->name,
            'role' => $created->role,
        ], Response::HTTP_CREATED);
    }
}
