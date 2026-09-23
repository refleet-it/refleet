<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Api\CompleteGitLabOAuth;

use App\Organization\GitLabConnection\Application\Command\ConnectGitLab\ConnectedGitLabConnection;
use App\Organization\GitLabConnection\Application\Command\ConnectGitLabViaOAuth\ConnectGitLabViaOAuthCommand;
use App\Organization\GitLabConnection\Domain\Connection\Exception\InvalidGitLabOAuthStateException;
use App\Organization\GitLabConnection\Domain\Connection\Exception\NotOrganizationOwnerException;
use App\Organization\GitLabConnection\Infrastructure\Security\GitLabOAuthStateSigner;
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

#[Route('/gitlab/connection/oauth/complete', name: 'gitlab_connection_oauth_complete', methods: ['POST'])]
#[OA\Post(
    description: 'Finish the OAuth flow started with /gitlab/connection/oauth/start: exchanges the code GitLab redirected back with and connects (or reconnects) the organization to the group named when the flow began. Owner only, and the same owner who started it.',
    summary: 'Complete GitLab OAuth',
    tags: ['GitLab Connection'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Connected'),
        new OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'State is invalid, expired or was issued to a different account'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Only the organization owner can connect GitLab'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, or OAuth is not configured'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'GitLab rejected the code or the group was not found'),
    ]
)]
final readonly class CompleteGitLabOAuthController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
        private GitLabOAuthStateSigner $stateSigner,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        CompleteGitLabOAuthRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        if ('owner' !== $organization->role) {
            throw new NotOrganizationOwnerException();
        }

        $state = $this->stateSigner->verify($payload->state);

        if ($state['accountId'] !== $user->getUserId()->asString() || $state['organizationId'] !== $organization->id) {
            throw new InvalidGitLabOAuthStateException('the authorization was started by a different account');
        }

        $handledStamp = $this->bus->dispatch(new ConnectGitLabViaOAuthCommand(
            organizationId: $organization->id,
            accountId: $user->getUserId()->asString(),
            code: $payload->code,
            groupPath: $state['groupPath'],
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
