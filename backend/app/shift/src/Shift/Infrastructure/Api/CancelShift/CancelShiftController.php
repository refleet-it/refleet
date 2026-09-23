<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\CancelShift;

use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use App\Shift\Shift\Application\Command\CancelShift\CancelShiftCommand;
use App\Shift\Shift\Application\Query\GetShift\GetShiftQuery;
use App\Shift\Shift\Application\Query\GetShift\ShiftDetail;
use App\Shift\Shift\Infrastructure\Api\GetShift\GetShiftController;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/shifts/{id}/cancel', name: 'shift_cancel', requirements: ['id' => Requirements::UUID], methods: ['POST'])]
#[OA\Post(
    description: 'Cancel a shift that is not yet in a terminal state. Cascades to non-terminal targets and any in-flight runner jobs.',
    summary: 'Cancel Shift',
    tags: ['Shift'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Shift ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Shift cancelled'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Shift not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, or shift is already in a terminal state'),
    ]
)]
final readonly class CancelShiftController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        string $id,
        #[MapRequestPayload]
        CancelShiftRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $this->bus->dispatch(new CancelShiftCommand(
            shiftId: $id,
            organizationId: $organization->id,
            reason: $payload->reason,
        ));

        $handledStamp = $this->bus->dispatch(new GetShiftQuery(
            shiftId: $id,
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var ShiftDetail $detail */
        $detail = $handledStamp?->getResult();

        return new JsonResponse(GetShiftController::toArray($detail), Response::HTTP_OK);
    }
}
