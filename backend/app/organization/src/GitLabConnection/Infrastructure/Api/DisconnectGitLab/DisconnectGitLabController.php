<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Api\DisconnectGitLab;

use App\Organization\GitLabConnection\Application\Command\DisconnectGitLab\DisconnectGitLabCommand;
use App\Organization\GitLabConnection\Domain\Connection\Exception\NotOrganizationOwnerException;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/gitlab/connection', name: 'gitlab_connection_disconnect', methods: ['DELETE'])]
#[OA\Delete(
    description: "Disconnect the current account's organization from GitLab. Already-registered projects are kept. Owner only.",
    summary: 'Disconnect GitLab',
    tags: ['GitLab Connection'],
    responses: [
        new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Disconnected'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Only the organization owner can disconnect GitLab'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'No GitLab connection to disconnect'),
    ]
)]
final readonly class DisconnectGitLabController
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

        if ('owner' !== $organization->role) {
            throw new NotOrganizationOwnerException();
        }

        $this->bus->dispatch(new DisconnectGitLabCommand(
            organizationId: $organization->id,
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
