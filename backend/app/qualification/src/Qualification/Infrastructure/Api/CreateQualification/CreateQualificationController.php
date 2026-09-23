<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\CreateQualification;

use App\Qualification\Qualification\Application\Command\CreateQualification\CreatedQualification;
use App\Qualification\Qualification\Application\Command\CreateQualification\CreateQualificationCommand;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/qualifications', name: 'qualification_create', methods: ['POST'])]
#[OA\Post(
    description: 'Draft a new qualification. Target projects are resolved immediately: an explicit projectIds list is used as-is, an empty/omitted list resolves to every project in the organization.',
    summary: 'Create Qualification',
    tags: ['Qualification'],
    responses: [
        new OA\Response(response: Response::HTTP_CREATED, description: 'Qualification drafted'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Invalid criteria or no target projects resolved'),
    ]
)]
final readonly class CreateQualificationController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        CreateQualificationRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $handledStamp = $this->bus->dispatch(new CreateQualificationCommand(
            organizationId: $organization->id,
            title: $payload->title,
            description: $payload->description,
            createdByAccountId: $user->getUserId()->asString(),
            qualificationMode: $payload->qualificationMode,
            qualificationEngine: $payload->qualificationEngine,
            qualificationPrompt: $payload->qualificationPrompt,
            qualificationModel: $payload->qualificationModel,
            projectIds: $payload->projectIds,
            qualificationRules: $payload->qualificationRules,
            qualificationSources: $payload->qualificationSources ?? [],
        ))->last(HandledStamp::class);

        /** @var CreatedQualification $created */
        $created = $handledStamp?->getResult();

        return new JsonResponse([
            'id' => $created->id,
            'title' => $created->title,
            'description' => $created->description,
            'status' => $created->status,
            'qualificationMode' => $created->qualificationMode,
            'targetCount' => $created->targetCount,
            'createdAt' => $created->createdAt,
        ], Response::HTTP_CREATED);
    }
}
