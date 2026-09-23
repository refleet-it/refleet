<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\ReportShiftMergeRequestStatus;

use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use App\Shift\Shift\Application\Command\ReportShiftMergeRequestStatus\ReportShiftMergeRequestStatusCommand;
use App\Shift\Shift\Application\Query\GetShiftTarget\GetShiftTargetQuery;
use App\Shift\Shift\Application\Query\GetShiftTarget\ShiftTargetDetail;
use App\Shift\Shift\Infrastructure\Api\GetShiftTarget\GetShiftTargetController;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/shifts/{id}/targets/{targetId}/merge-request', name: 'shift_target_merge_request', requirements: ['id' => Requirements::UUID, 'targetId' => Requirements::UUID], methods: ['POST'])]
#[OA\Post(
    description: 'Report a merge request status change for a shift target (opened/merged/closed). Skeleton for a future GitLab webhook; a merged/closed report also re-checks whether the whole shift has become terminal. Late reports on an already-terminal target are a no-op.',
    summary: 'Report Shift Merge Request Status',
    tags: ['Shift'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Shift ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        new OA\Parameter(name: 'targetId', description: 'Shift Target ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Merge request status recorded'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Shift or shift target not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'The API key owner does not belong to an organization'),
    ]
)]
final readonly class ReportShiftMergeRequestStatusController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        string $id,
        string $targetId,
        #[MapRequestPayload]
        ReportShiftMergeRequestStatusRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $this->bus->dispatch(new ReportShiftMergeRequestStatusCommand(
            shiftTargetId: $targetId,
            organizationId: $organization->id,
            status: $payload->status,
            url: $payload->url,
            externalIid: $payload->externalIid,
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
