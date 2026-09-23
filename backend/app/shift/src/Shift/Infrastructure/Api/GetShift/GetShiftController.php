<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\GetShift;

use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use App\Shift\Shift\Application\Query\GetShift\GetShiftQuery;
use App\Shift\Shift\Application\Query\GetShift\ShiftDetail;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/shifts/{id}', name: 'shift_get', requirements: ['id' => Requirements::UUID], methods: ['GET'])]
#[OA\Get(
    description: 'Get a single shift, including the current status breakdown of its targets.',
    summary: 'Get Shift',
    tags: ['Shift'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Shift ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Shift detail'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Shift not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class GetShiftController
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

        $handledStamp = $this->bus->dispatch(new GetShiftQuery(
            shiftId: $id,
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var ShiftDetail $detail */
        $detail = $handledStamp?->getResult();

        return new JsonResponse(self::toArray($detail), Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(ShiftDetail $detail): array
    {
        return [
            'id' => $detail->id,
            'organizationId' => $detail->organizationId,
            'title' => $detail->title,
            'description' => $detail->description,
            'createdBy' => $detail->createdBy,
            'status' => $detail->status,
            'qualificationId' => $detail->qualificationId,
            'changeMode' => $detail->changeMode,
            'changeEngine' => $detail->changeEngine,
            'changePrompt' => $detail->changePrompt,
            'changeModel' => $detail->changeModel,
            'changeRules' => $detail->changeRules,
            'changeSources' => $detail->changeSources,
            'cancelReason' => $detail->cancelReason,
            'targetCount' => $detail->targetCount,
            'statusBreakdown' => $detail->statusBreakdown,
            'progressPercent' => $detail->progressPercent,
            'createdAt' => $detail->createdAt,
            'changeStartedAt' => $detail->changeStartedAt,
            'completedAt' => $detail->completedAt,
            'cancelledAt' => $detail->cancelledAt,
            'archivedAt' => $detail->archivedAt,
        ];
    }
}
