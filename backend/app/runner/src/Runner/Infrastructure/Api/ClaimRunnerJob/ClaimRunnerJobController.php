<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\ClaimRunnerJob;

use App\Runner\Runner\Application\Command\ClaimRunnerJob\ClaimedRunnerJob;
use App\Runner\Runner\Application\Command\ClaimRunnerJob\ClaimRunnerJobCommand;
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

#[Route('/runner/jobs/claim', name: 'runner_job_claim', methods: ['POST'])]
#[OA\Post(
    description: 'Claim the next available runner job for the calling organization (resolved from the API key used to authenticate). Also reclaims jobs whose lease expired without a report. Returns 204 when there is no work.',
    summary: 'Claim Runner Job',
    tags: ['Runner'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'A job was claimed'),
        new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'No job is currently available'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'The API key owner does not belong to an organization'),
    ]
)]
final readonly class ClaimRunnerJobController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        ClaimRunnerJobRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $handledStamp = $this->bus->dispatch(new ClaimRunnerJobCommand(
            runnerId: $payload->runnerId,
            organizationId: $organization->id,
            supportedKinds: $payload->supportedKinds,
            supportedModes: $payload->supportedModes,
            supportedEngines: $payload->supportedEngines,
        ))->last(HandledStamp::class);

        /** @var ClaimedRunnerJob|null $claimed */
        $claimed = $handledStamp?->getResult();

        if (null === $claimed) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse([
            'jobId' => $claimed->jobId,
            'ownerId' => $claimed->ownerId,
            'ownerTargetId' => $claimed->ownerTargetId,
            'ownerLabel' => $claimed->ownerLabel,
            'kind' => $claimed->kind,
            'mode' => $claimed->mode,
            'payload' => $claimed->payload,
            'leaseExpiresAt' => $claimed->leaseExpiresAt,
        ], Response::HTTP_OK);
    }
}
