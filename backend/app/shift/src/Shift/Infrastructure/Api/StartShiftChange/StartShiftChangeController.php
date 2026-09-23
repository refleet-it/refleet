<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\StartShiftChange;

use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use App\Shift\Shift\Application\Command\StartShiftChange\StartShiftChangeCommand;
use App\Shift\Shift\Application\Query\GetShift\GetShiftQuery;
use App\Shift\Shift\Application\Query\GetShift\ShiftDetail;
use App\Shift\Shift\Infrastructure\Api\GetShift\GetShiftController;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/shifts/{id}/change/start', name: 'shift_change_start', requirements: ['id' => Requirements::UUID], methods: ['POST'])]
#[OA\Post(
    description: 'Start the change: enqueues one runner job per target and moves the shift to APPLYING_CHANGE.',
    summary: 'Start Shift Change',
    tags: ['Shift'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Shift ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Change started'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Shift not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, change criteria not defined, or shift is not in a state that allows starting the change'),
    ]
)]
final readonly class StartShiftChangeController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        string $id,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $this->bus->dispatch(new StartShiftChangeCommand(
            shiftId: $id,
            organizationId: $organization->id,
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
