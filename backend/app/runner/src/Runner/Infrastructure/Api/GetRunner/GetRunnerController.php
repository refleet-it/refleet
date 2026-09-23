<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\GetRunner;

use App\Runner\Runner\Application\Query\GetRunner\GetRunnerQuery;
use App\Runner\Runner\Application\Query\GetRunner\RunnerDetail;
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

#[Route('/runners/{id}', name: 'runner_get', requirements: ['id' => Requirements::UUID], methods: ['GET'])]
#[OA\Get(
    description: 'Get a single runner, identified by name, with its current status.',
    summary: 'Get Runner',
    tags: ['Runner'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Runner ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Runner detail'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Runner not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class GetRunnerController
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

        $handledStamp = $this->bus->dispatch(new GetRunnerQuery(
            runnerId: $id,
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var RunnerDetail $detail */
        $detail = $handledStamp?->getResult();

        return new JsonResponse(self::toArray($detail), Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(RunnerDetail $detail): array
    {
        return [
            'id' => $detail->id,
            'name' => $detail->name,
            'status' => $detail->status,
            'lastSeenAt' => $detail->lastSeenAt,
            'createdAt' => $detail->createdAt,
            'archivedAt' => $detail->archivedAt,
            'supportedEngines' => $detail->supportedEngines,
            'supportedModels' => $detail->supportedModels,
            'usage' => $detail->usage,
            'version' => $detail->version,
            'latestVersion' => $detail->latestVersion,
            'updateAvailable' => $detail->updateAvailable,
            'updateRequestedAt' => $detail->updateRequestedAt,
        ];
    }
}
