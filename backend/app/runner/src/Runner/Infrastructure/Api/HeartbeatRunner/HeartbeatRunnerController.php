<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\HeartbeatRunner;

use App\Runner\Runner\Application\Command\HeartbeatRunner\HeartbeatedRunner;
use App\Runner\Runner\Application\Command\HeartbeatRunner\HeartbeatRunnerCommand;
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

#[Route('/runner/heartbeat', name: 'runner_heartbeat', methods: ['POST'])]
#[OA\Post(
    description: 'Register the runner (on its first call) or refresh its last-seen heartbeat, for the calling organization (resolved from the API key used to authenticate). The answer carries the newest published runner version, whether this runner is behind it, and — once — whether an update was requested from the dashboard.',
    summary: 'Heartbeat Runner',
    tags: ['Runner'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Runner registered or refreshed'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class HeartbeatRunnerController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        HeartbeatRunnerRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $handledStamp = $this->bus->dispatch(new HeartbeatRunnerCommand(
            organizationId: $organization->id,
            name: $payload->name,
            apiKeyId: $user->getApiKeyId(),
            supportedEngines: $payload->supportedEngines,
            supportedModels: $payload->supportedModels,
            usage: $payload->usage?->toArray(),
            version: $payload->version,
        ))->last(HandledStamp::class);

        /** @var HeartbeatedRunner $runner */
        $runner = $handledStamp?->getResult();

        return new JsonResponse([
            'id' => $runner->id,
            'name' => $runner->name,
            'status' => $runner->status,
            'lastSeenAt' => $runner->lastSeenAt,
            'supportedEngines' => $runner->supportedEngines,
            'supportedModels' => $runner->supportedModels,
            'usage' => $runner->usage,
            'version' => $runner->version,
            'latestVersion' => $runner->latestVersion,
            'updateAvailable' => $runner->updateAvailable,
            'updateRequested' => $runner->updateRequested,
        ], Response::HTTP_OK);
    }
}
