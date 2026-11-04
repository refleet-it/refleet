<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\RetryPipelineFix;

use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use App\Shift\Shift\Application\Command\RetryPipelineFix\RetryPipelineFixCommand;
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

#[Route('/shifts/{id}/targets/{targetId}/pipeline-fix/retry', name: 'shift_target_pipeline_fix_retry', requirements: ['id' => Requirements::UUID, 'targetId' => Requirements::UUID], methods: ['POST'])]
#[OA\Post(
    description: "Give a target whose pipeline fix gave up a fresh set of attempts. The merge request's branch is left alone, and the next poll decides from the pipeline's current state whether to queue a fix.",
    summary: 'Retry Pipeline Fix',
    tags: ['Shift'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Shift ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        new OA\Parameter(name: 'targetId', description: 'Shift Target ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'The target is being watched again'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Shift or shift target not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, the shift is archived, or the target has no pipeline fix that gave up'),
    ]
)]
final readonly class RetryPipelineFixController
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

        $this->bus->dispatch(new RetryPipelineFixCommand(
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
