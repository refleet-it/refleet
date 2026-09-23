<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Api\ConnectGitLab;

use App\Organization\GitLabConnection\Application\Command\ConnectGitLab\ConnectedGitLabConnection;
use App\Organization\GitLabConnection\Application\Command\ConnectGitLab\ConnectGitLabCommand;
use App\Organization\GitLabConnection\Domain\Connection\Exception\NotOrganizationOwnerException;
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

#[Route('/gitlab/connection', name: 'gitlab_connection_connect', methods: ['POST'])]
#[OA\Post(
    description: "Connect (or reconnect) the current account's organization to a GitLab group. Owner only.",
    summary: 'Connect GitLab',
    tags: ['GitLab Connection'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Connected'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Only the organization owner can connect GitLab'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Invalid access token or group path'),
    ]
)]
final readonly class ConnectGitLabController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        ConnectGitLabRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        if ('owner' !== $organization->role) {
            throw new NotOrganizationOwnerException();
        }

        $handledStamp = $this->bus->dispatch(new ConnectGitLabCommand(
            organizationId: $organization->id,
            accountId: $user->getUserId()->asString(),
            groupPath: $payload->groupPath,
            accessToken: $payload->accessToken,
            baseUrl: $payload->baseUrl,
        ))->last(HandledStamp::class);

        /** @var ConnectedGitLabConnection $connected */
        $connected = $handledStamp?->getResult();

        return new JsonResponse([
            'connected' => true,
            'baseUrl' => $connected->baseUrl,
            'groupPath' => $connected->groupPath,
            'groupName' => $connected->groupName,
            'connectedAt' => $connected->connectedAt,
        ], Response::HTTP_OK);
    }
}
