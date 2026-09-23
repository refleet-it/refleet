<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\RetryQualificationTargets;

use App\Qualification\Qualification\Application\Command\RetryQualificationTargets\RetryQualificationTargetsCommand;
use App\Qualification\Qualification\Application\Query\GetQualification\GetQualificationQuery;
use App\Qualification\Qualification\Application\Query\GetQualification\QualificationDetail;
use App\Qualification\Qualification\Infrastructure\Api\GetQualification\GetQualificationController;
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

#[Route('/qualifications/{id}/retry', name: 'qualification_retry', requirements: ['id' => Requirements::UUID], methods: ['POST'])]
#[OA\Post(
    description: 'Re-enqueue a runner job for every FAILED target of the qualification. A completed qualification goes back to RUNNING until the retried targets settle; a running one just absorbs them.',
    summary: 'Retry Failed Qualification Targets',
    tags: ['Qualification'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Qualification ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Failed targets re-enqueued (or none to retry)'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Qualification not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, or the qualification is archived, cancelled or not started'),
    ]
)]
final readonly class RetryQualificationTargetsController
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

        $this->bus->dispatch(new RetryQualificationTargetsCommand(
            qualificationId: $id,
            organizationId: $organization->id,
        ));

        $handledStamp = $this->bus->dispatch(new GetQualificationQuery(
            qualificationId: $id,
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var QualificationDetail $detail */
        $detail = $handledStamp?->getResult();

        return new JsonResponse(GetQualificationController::toArray($detail), Response::HTTP_OK);
    }
}
