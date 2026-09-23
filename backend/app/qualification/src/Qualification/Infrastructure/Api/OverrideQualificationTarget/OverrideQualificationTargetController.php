<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\OverrideQualificationTarget;

use App\Qualification\Qualification\Application\Command\OverrideQualificationTarget\OverrideQualificationTargetCommand;
use App\Qualification\Qualification\Application\Query\GetQualificationTarget\GetQualificationTargetQuery;
use App\Qualification\Qualification\Application\Query\GetQualificationTarget\QualificationTargetDetail;
use App\Qualification\Qualification\Infrastructure\Api\GetQualificationTarget\GetQualificationTargetController;
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

#[Route('/qualifications/{id}/targets/{targetId}/override', name: 'qualification_target_override', requirements: ['id' => Requirements::UUID, 'targetId' => Requirements::UUID], methods: ['POST'])]
#[OA\Post(
    description: "Manually set (or flip) a single target's qualification decision, superseding any regex/AI result. Allowed at any time except while a runner job is actually in flight for that target.",
    summary: 'Override Qualification Target',
    tags: ['Qualification'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Qualification ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        new OA\Parameter(name: 'targetId', description: 'Qualification Target ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Qualification overridden'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Qualification or qualification target not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, or the target has a runner job in flight'),
    ]
)]
final readonly class OverrideQualificationTargetController
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
        OverrideQualificationTargetRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $this->bus->dispatch(new OverrideQualificationTargetCommand(
            qualificationId: $id,
            organizationId: $organization->id,
            targetId: $targetId,
            qualified: $payload->qualified,
            note: $payload->note,
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
