<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\GetShiftTarget;

use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use App\Shift\Shift\Application\Query\GetShiftTarget\GetShiftTargetQuery;
use App\Shift\Shift\Application\Query\GetShiftTarget\ShiftTargetDetail;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/shifts/{id}/targets/{targetId}', name: 'shift_target_get', requirements: ['id' => Requirements::UUID, 'targetId' => Requirements::UUID], methods: ['GET'])]
#[OA\Get(
    description: 'Get a single shift target, including its project snapshot, change result and merge request state.',
    summary: 'Get Shift Target',
    tags: ['Shift'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Shift ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        new OA\Parameter(name: 'targetId', description: 'Shift Target ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Shift target detail'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Shift or shift target not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class GetShiftTargetController
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

        $handledStamp = $this->bus->dispatch(new GetShiftTargetQuery(
            shiftTargetId: $targetId,
            shiftId: $id,
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var ShiftTargetDetail $detail */
        $detail = $handledStamp?->getResult();

        return new JsonResponse(self::toArray($detail), Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(ShiftTargetDetail $detail): array
    {
        return [
            'id' => $detail->id,
            'shiftId' => $detail->shiftId,
            'organizationId' => $detail->organizationId,
            'projectId' => $detail->projectId,
            'projectSnapshot' => [
                'externalId' => $detail->projectSnapshotExternalId,
                'path' => $detail->projectSnapshotPath,
                'name' => $detail->projectSnapshotName,
                'defaultBranch' => $detail->projectSnapshotDefaultBranch,
            ],
            'status' => $detail->status,
            'changeSummary' => $detail->changeSummary,
            'changeBranchName' => $detail->changeBranchName,
            'runnerJobId' => $detail->runnerJobId,
            'runnerName' => $detail->runnerName,
            'changeStartedAt' => $detail->changeStartedAt,
            'changeCompletedAt' => $detail->changeCompletedAt,
            'mergeRequestUrl' => $detail->mergeRequestUrl,
            'mergeRequestExternalIid' => $detail->mergeRequestExternalIid,
            'mergeRequestStatus' => $detail->mergeRequestStatus,
            'mergeRequestUpdatedAt' => $detail->mergeRequestUpdatedAt,
            'createdAt' => $detail->createdAt,
        ];
    }
}
