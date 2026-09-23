<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\GetQualificationTarget;

use App\Qualification\Qualification\Application\Query\GetQualificationTarget\GetQualificationTargetQuery;
use App\Qualification\Qualification\Application\Query\GetQualificationTarget\QualificationTargetDetail;
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

#[Route('/qualifications/{id}/targets/{targetId}', name: 'qualification_target_get', requirements: ['id' => Requirements::UUID, 'targetId' => Requirements::UUID], methods: ['GET'])]
#[OA\Get(
    description: 'Get a single qualification target, including its project snapshot and result.',
    summary: 'Get Qualification Target',
    tags: ['Qualification'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Qualification ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        new OA\Parameter(name: 'targetId', description: 'Qualification Target ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Qualification target detail'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Qualification or qualification target not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class GetQualificationTargetController
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

        $handledStamp = $this->bus->dispatch(new GetQualificationTargetQuery(
            targetId: $targetId,
            qualificationId: $id,
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var QualificationTargetDetail $detail */
        $detail = $handledStamp?->getResult();

        return new JsonResponse(self::toArray($detail), Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(QualificationTargetDetail $detail): array
    {
        return [
            'id' => $detail->id,
            'qualificationId' => $detail->qualificationId,
            'organizationId' => $detail->organizationId,
            'projectId' => $detail->projectId,
            'projectSnapshot' => [
                'externalId' => $detail->projectSnapshotExternalId,
                'path' => $detail->projectSnapshotPath,
                'name' => $detail->projectSnapshotName,
                'defaultBranch' => $detail->projectSnapshotDefaultBranch,
            ],
            'status' => $detail->status,
            'summary' => $detail->summary,
            'score' => $detail->score,
            'overridden' => $detail->overridden,
            'overrideNote' => $detail->overrideNote,
            'runnerJobId' => $detail->runnerJobId,
            'runnerName' => $detail->runnerName,
            'startedAt' => $detail->startedAt,
            'completedAt' => $detail->completedAt,
            'createdAt' => $detail->createdAt,
        ];
    }
}
