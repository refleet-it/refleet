<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\CreateShift;

use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shift\Shift\Application\Command\CreateShift\CreatedShift;
use App\Shift\Shift\Application\Command\CreateShift\CreateShiftCommand;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/shifts', name: 'shift_create', methods: ['POST'])]
#[OA\Post(
    description: 'Draft a new shift. Target projects are resolved immediately from a qualification, a subset of a qualification, or a fully manual selection — see the request schema.',
    summary: 'Create Shift',
    tags: ['Shift'],
    responses: [
        new OA\Response(response: Response::HTTP_CREATED, description: 'Shift drafted'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'No target projects resolved, or a manual shift was requested without an explicit project selection'),
    ]
)]
final readonly class CreateShiftController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        CreateShiftRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $handledStamp = $this->bus->dispatch(new CreateShiftCommand(
            organizationId: $organization->id,
            title: $payload->title,
            description: $payload->description,
            createdByAccountId: $user->getUserId()->asString(),
            qualificationId: $payload->qualificationId,
            projectIds: $payload->projectIds,
        ))->last(HandledStamp::class);

        /** @var CreatedShift $created */
        $created = $handledStamp?->getResult();

        return new JsonResponse([
            'id' => $created->id,
            'title' => $created->title,
            'description' => $created->description,
            'status' => $created->status,
            'qualificationId' => $created->qualificationId,
            'targetCount' => $created->targetCount,
            'createdAt' => $created->createdAt,
        ], Response::HTTP_CREATED);
    }
}
