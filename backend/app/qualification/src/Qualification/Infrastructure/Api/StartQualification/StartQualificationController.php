<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\StartQualification;

use App\Qualification\Qualification\Application\Command\StartQualification\StartQualificationCommand;
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

#[Route('/qualifications/{id}/start', name: 'qualification_start', requirements: ['id' => Requirements::UUID], methods: ['POST'])]
#[OA\Post(
    description: 'Start the qualification: enqueues one runner job per pending target and moves the qualification to RUNNING.',
    summary: 'Start Qualification',
    tags: ['Qualification'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Qualification ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Qualification started'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Qualification not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, or qualification is not in a state that allows starting'),
    ]
)]
final readonly class StartQualificationController
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

        $this->bus->dispatch(new StartQualificationCommand(
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
