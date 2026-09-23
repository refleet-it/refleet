<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Api\StartGitLabOAuth;

use App\Organization\GitLabConnection\Domain\Connection\Exception\GitLabOAuthNotConfiguredException;
use App\Organization\GitLabConnection\Domain\Connection\Exception\NotOrganizationOwnerException;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabOAuthClientInterface;
use App\Organization\GitLabConnection\Infrastructure\Security\GitLabOAuthStateSigner;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/gitlab/connection/oauth/start', name: 'gitlab_connection_oauth_start', methods: ['POST'])]
#[OA\Post(
    description: 'Begin connecting the organization to a gitlab.com group through OAuth: returns the GitLab authorization URL to send the owner to. Owner only.',
    summary: 'Start GitLab OAuth',
    tags: ['GitLab Connection'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Authorization URL'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Only the organization owner can connect GitLab'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, or OAuth is not configured'),
    ]
)]
final readonly class StartGitLabOAuthController
{
    public function __construct(
        private OrganizationContextProviderInterface $organizationContext,
        private GitLabOAuthClientInterface $oauthClient,
        private GitLabOAuthStateSigner $stateSigner,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        StartGitLabOAuthRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        if ('owner' !== $organization->role) {
            throw new NotOrganizationOwnerException();
        }

        if (!$this->oauthClient->isConfigured()) {
            throw new GitLabOAuthNotConfiguredException();
        }

        $state = $this->stateSigner->sign($organization->id, $user->getUserId()->asString(), \trim($payload->groupPath));

        return new JsonResponse(['url' => $this->oauthClient->authorizationUrl($state)], Response::HTTP_OK);
    }
}
