<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\ArchiveRunner;

use App\Runner\Runner\Application\Command\ArchiveRunner\ArchiveRunnerCommand;
use App\Runner\Runner\Application\Query\GetRunner\GetRunnerQuery;
use App\Runner\Runner\Application\Query\GetRunner\RunnerDetail;
use App\Runner\Runner\Infrastructure\Api\GetRunner\GetRunnerController;
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

#[Route('/runners/{id}/archive', name: 'runner_archive', requirements: ['id' => Requirements::UUID], methods: ['POST'])]
#[OA\Post(
    description: 'Archive a runner: it leaves the live runner fleet but keeps its job history, and the API key it authenticated with is revoked. Cannot be undone.',
    summary: 'Archive Runner',
    tags: ['Runner'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Runner ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Runner archived'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Runner not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, or runner is already archived'),
    ]
)]
final readonly class ArchiveRunnerController
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

        $this->bus->dispatch(new ArchiveRunnerCommand(
            runnerId: $id,
            organizationId: $organization->id,
        ));

        $handledStamp = $this->bus->dispatch(new GetRunnerQuery(
            runnerId: $id,
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var RunnerDetail $detail */
        $detail = $handledStamp?->getResult();

        return new JsonResponse(GetRunnerController::toArray($detail), Response::HTTP_OK);
    }
}
