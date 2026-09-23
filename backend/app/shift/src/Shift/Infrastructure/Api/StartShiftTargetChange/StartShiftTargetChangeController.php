<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\StartShiftTargetChange;

use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use App\Shift\Shift\Application\Command\StartShiftTargetChange\StartShiftTargetChangeCommand;
use App\Shift\Shift\Application\Query\GetShiftTarget\GetShiftTargetQuery;
use App\Shift\Shift\Application\Query\GetShiftTarget\ShiftTargetDetail;
use App\Shift\Shift\Infrastructure\Api\GetShiftTarget\GetShiftTargetController;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/shifts/{id}/targets/{targetId}/change/start', name: 'shift_target_change_start', requirements: ['id' => Requirements::UUID, 'targetId' => Requirements::UUID], methods: ['POST'])]
#[OA\Post(
    description: 'Run the change on this one target: a trial run while the shift is still a draft (the criteria stay editable and the other targets untouched), or a re-run of a target that failed, has an open/closed merge request or changed nothing. A re-run refreshes the existing merge request instead of opening a new one. Re-running a target of a completed shift re-opens the shift until the target settles.',
    summary: 'Start Shift Target Change',
    tags: ['Shift'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Shift ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        new OA\Parameter(name: 'targetId', description: 'Shift Target ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Change started for the target'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Shift or shift target not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, change criteria not defined, or the shift/target is not in a state that allows running the change'),
    ]
)]
final readonly class StartShiftTargetChangeController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        string $id,
        string $targetId,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $this->bus->dispatch(new StartShiftTargetChangeCommand(
            shiftId: $id,
            shiftTargetId: $targetId,
            organizationId: $organization->id,
        ));

        $handledStamp = $this->bus->dispatch(new GetShiftTargetQuery(
            shiftTargetId: $targetId,
            shiftId: $id,
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var ShiftTargetDetail $detail */
        $detail = $handledStamp?->getResult();

        return new JsonResponse(GetShiftTargetController::toArray($detail), Response::HTTP_OK);
    }
}
