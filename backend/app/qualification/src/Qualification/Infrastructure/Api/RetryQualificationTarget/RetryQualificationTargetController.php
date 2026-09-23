<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\RetryQualificationTarget;

use App\Qualification\Qualification\Application\Command\RetryQualificationTargets\RetryQualificationTargetsCommand;
use App\Qualification\Qualification\Application\Query\GetQualificationTarget\GetQualificationTargetQuery;
use App\Qualification\Qualification\Application\Query\GetQualificationTarget\QualificationTargetDetail;
use App\Qualification\Qualification\Infrastructure\Api\GetQualificationTarget\GetQualificationTargetController;
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

#[Route('/qualifications/{id}/targets/{targetId}/retry', name: 'qualification_target_retry', requirements: ['id' => Requirements::UUID, 'targetId' => Requirements::UUID], methods: ['POST'])]
#[OA\Post(
    description: 'Re-enqueue a runner job for one FAILED target. A completed qualification goes back to RUNNING until the target settles.',
    summary: 'Retry Qualification Target',
    tags: ['Qualification'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Qualification ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        new OA\Parameter(name: 'targetId', description: 'Qualification Target ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Target re-enqueued'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Qualification or qualification target not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, the target is not FAILED, or the qualification is archived, cancelled or not started'),
    ]
)]
final readonly class RetryQualificationTargetController
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

        $this->bus->dispatch(new RetryQualificationTargetsCommand(
            qualificationId: $id,
            organizationId: $organization->id,
            targetId: $targetId,
        ));

        $handledStamp = $this->bus->dispatch(new GetQualificationTargetQuery(
            targetId: $targetId,
            qualificationId: $id,
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var QualificationTargetDetail $detail */
        $detail = $handledStamp?->getResult();

        return new JsonResponse(GetQualificationTargetController::toArray($detail), Response::HTTP_OK);
    }
}
