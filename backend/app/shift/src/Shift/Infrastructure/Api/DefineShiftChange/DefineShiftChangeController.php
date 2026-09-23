<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\DefineShiftChange;

use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use App\Shift\Shift\Application\Command\DefineShiftChange\DefineShiftChangeCommand;
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

#[Route('/shifts/{id}/change', name: 'shift_define_change', requirements: ['id' => Requirements::UUID], methods: ['POST'])]
#[OA\Post(
    description: 'Define (or redefine) the change criteria applied to every target. May be called multiple times before the change is started.',
    summary: 'Define Shift Change',
    tags: ['Shift'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Shift ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Change criteria defined'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Shift not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, or shift is not in a state that allows redefining the change'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Invalid change criteria'),
    ]
)]
final readonly class DefineShiftChangeController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        string $id,
        #[MapRequestPayload]
        DefineShiftChangeRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $this->bus->dispatch(new DefineShiftChangeCommand(
            shiftId: $id,
            organizationId: $organization->id,
            changeMode: $payload->changeMode,
            changeEngine: $payload->changeEngine,
            changePrompt: $payload->changePrompt,
            changeModel: $payload->changeModel,
            changeRules: $payload->changeRules,
            changeSources: $payload->changeSources ?? [],
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
