<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\CancelQualification;

use App\Qualification\Qualification\Application\Command\CancelQualification\CancelQualificationCommand;
use App\Qualification\Qualification\Application\Query\GetQualification\GetQualificationQuery;
use App\Qualification\Qualification\Application\Query\GetQualification\QualificationDetail;
use App\Qualification\Qualification\Infrastructure\Api\GetQualification\GetQualificationController;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/qualifications/{id}/cancel', name: 'qualification_cancel', requirements: ['id' => Requirements::UUID], methods: ['POST'])]
#[OA\Post(
    description: 'Cancel a qualification that is not yet in a terminal state. Cascades to non-terminal targets and any in-flight runner jobs.',
    summary: 'Cancel Qualification',
    tags: ['Qualification'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Qualification ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Qualification cancelled'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Qualification not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, or qualification is already in a terminal state'),
    ]
)]
final readonly class CancelQualificationController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        string $id,
        #[MapRequestPayload]
        CancelQualificationRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $this->bus->dispatch(new CancelQualificationCommand(
            qualificationId: $id,
            organizationId: $organization->id,
            reason: $payload->reason,
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
