<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\GetAvailableModels;

use App\Runner\Runner\Application\Query\GetAvailableModels\AvailableModels;
use App\Runner\Runner\Application\Query\GetAvailableModels\GetAvailableModelsQuery;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/runners/available-models', name: 'runner_available_models', methods: ['GET'])]
#[OA\Get(
    description: "Model ids currently offered across the organization's non-archived runner fleet, grouped by engine — sourced from what each runner reported on heartbeat (see HeartbeatRunner), not a fixed application-level list.",
    summary: 'Get Available Models',
    tags: ['Runner'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Model ids grouped by engine'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class GetAvailableModelsController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $handledStamp = $this->bus->dispatch(new GetAvailableModelsQuery(
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var AvailableModels $models */
        $models = $handledStamp?->getResult();

        return new JsonResponse([
            'claude' => $models->claude,
            'kiro' => $models->kiro,
        ], Response::HTTP_OK);
    }
}
