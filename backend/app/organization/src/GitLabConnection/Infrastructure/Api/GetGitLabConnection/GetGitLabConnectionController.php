<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Api\GetGitLabConnection;

use App\Organization\GitLabConnection\Application\Query\GetGitLabConnection\GetGitLabConnectionQuery;
use App\Organization\GitLabConnection\Application\Query\GetGitLabConnection\GitLabConnectionOverview;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabOAuthClientInterface;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/gitlab/connection', name: 'gitlab_connection_get', methods: ['GET'])]
#[OA\Get(
    description: "Get the current account's organization GitLab connection status, if any.",
    summary: 'Get GitLab Connection',
    tags: ['GitLab Connection'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Connection status (connected: false if never connected)'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class GetGitLabConnectionController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
        private GitLabOAuthClientInterface $oauthClient,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $handledStamp = $this->bus->dispatch(new GetGitLabConnectionQuery(
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var GitLabConnectionOverview|null $connection */
        $connection = $handledStamp?->getResult();

        if (null === $connection) {
            return new JsonResponse(['connected' => false, 'oauthAvailable' => $this->oauthClient->isConfigured()], Response::HTTP_OK);
        }

        return new JsonResponse([
            'connected' => true,
            'oauthAvailable' => $this->oauthClient->isConfigured(),
            'authMethod' => $connection->authMethod,
            'baseUrl' => $connection->baseUrl,
            'groupPath' => $connection->groupPath,
            'groupName' => $connection->groupName,
            'connectedAt' => $connection->connectedAt,
            'lastSyncedAt' => $connection->lastSyncedAt,
            'lastSyncStatus' => $connection->lastSyncStatus,
            'lastSyncError' => $connection->lastSyncError,
            'lastSyncProjectCount' => $connection->lastSyncProjectCount,
        ], Response::HTTP_OK);
    }
}
