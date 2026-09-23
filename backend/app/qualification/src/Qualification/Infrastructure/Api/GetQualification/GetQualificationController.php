<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\GetQualification;

use App\Qualification\Qualification\Application\Query\GetQualification\GetQualificationQuery;
use App\Qualification\Qualification\Application\Query\GetQualification\QualificationDetail;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/qualifications/{id}', name: 'qualification_get', requirements: ['id' => Requirements::UUID], methods: ['GET'])]
#[OA\Get(
    description: 'Get a single qualification, including the current status breakdown of its targets.',
    summary: 'Get Qualification',
    tags: ['Qualification'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Qualification ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Qualification detail'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Qualification not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class GetQualificationController
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

        $handledStamp = $this->bus->dispatch(new GetQualificationQuery(
            qualificationId: $id,
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var QualificationDetail $detail */
        $detail = $handledStamp?->getResult();

        return new JsonResponse(self::toArray($detail), Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(QualificationDetail $detail): array
    {
        return [
            'id' => $detail->id,
            'organizationId' => $detail->organizationId,
            'title' => $detail->title,
            'description' => $detail->description,
            'createdBy' => $detail->createdBy,
            'status' => $detail->status,
            'qualificationMode' => $detail->qualificationMode,
            'qualificationEngine' => $detail->qualificationEngine,
            'qualificationPrompt' => $detail->qualificationPrompt,
            'qualificationModel' => $detail->qualificationModel,
            'qualificationRules' => $detail->qualificationRules,
            'qualificationSources' => $detail->qualificationSources,
            'cancelReason' => $detail->cancelReason,
            'targetCount' => $detail->targetCount,
            'statusBreakdown' => $detail->statusBreakdown,
            'progressPercent' => $detail->progressPercent,
            'createdAt' => $detail->createdAt,
            'startedAt' => $detail->startedAt,
            'completedAt' => $detail->completedAt,
            'cancelledAt' => $detail->cancelledAt,
            'archivedAt' => $detail->archivedAt,
        ];
    }
}
