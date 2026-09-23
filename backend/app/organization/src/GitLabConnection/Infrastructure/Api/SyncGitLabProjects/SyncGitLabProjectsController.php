<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Api\SyncGitLabProjects;

use App\Organization\GitLabConnection\Application\Command\SyncGitLabProjects\SyncedGitLabProjects;
use App\Organization\GitLabConnection\Application\Command\SyncGitLabProjects\SyncGitLabProjectsCommand;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/gitlab/connection/sync', name: 'gitlab_connection_sync', methods: ['POST'])]
#[OA\Post(
    description: "Fetch every project from the connected GitLab group (including subgroups) and register/re-sync each one into the organization's fleet.",
    summary: 'Sync GitLab Projects',
    tags: ['GitLab Connection'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Sync result'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Organization is not connected to GitLab'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'GitLab rejected the stored credentials'),
    ]
)]
final readonly class SyncGitLabProjectsController
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

        $handledStamp = $this->bus->dispatch(new SyncGitLabProjectsCommand(
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var SyncedGitLabProjects $result */
        $result = $handledStamp?->getResult();

        return new JsonResponse([
            'syncedCount' => $result->syncedCount,
            'failedCount' => $result->failedCount,
            'archivedCount' => $result->archivedCount,
            'lastSyncedAt' => $result->lastSyncedAt,
        ], Response::HTTP_OK);
    }
}
