<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Api\GetGitLabCredentialsForRunner;

use App\Organization\GitLabConnection\Application\Query\GetGitLabCredentialsForRunner\GetGitLabCredentialsForRunnerQuery;
use App\Organization\GitLabConnection\Application\Query\GetGitLabCredentialsForRunner\GitLabRunnerCredentials;
use App\Organization\GitLabConnection\Domain\Connection\Exception\GitLabCredentialsRequireApiKeyException;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/runner/gitlab-credentials', name: 'runner_gitlab_credentials', methods: ['GET'])]
#[OA\Get(
    description: "Get the GitLab base URL and a currently valid access token for the calling organization's connected GitLab account (resolved from the API key used to authenticate), so a runner fleet member never needs its own static GitLab credentials. Refused for browser sessions: only API-key callers, i.e. runners, are handed the token.",
    summary: 'Get GitLab Credentials For Runner',
    tags: ['Runner'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'GitLab base URL and access token'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Caller authenticated with a browser session instead of an API key'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Organization is not connected to GitLab'),
    ]
)]
final readonly class GetGitLabCredentialsForRunnerController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        if (null === $user->getApiKeyId()) {
            throw new GitLabCredentialsRequireApiKeyException();
        }

        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $handledStamp = $this->bus->dispatch(new GetGitLabCredentialsForRunnerQuery(
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var GitLabRunnerCredentials $credentials */
        $credentials = $handledStamp?->getResult();

        // The one place the group token leaves the backend in plaintext: if it ever turns up
        // where it should not, this is the trail back to the API key that fetched it.
        $this->logger->info('GitLab access token handed to a runner', [
            'organizationId' => $organization->id,
            'accountId' => $user->getUserId()->asString(),
            'apiKeyId' => $user->getApiKeyId(),
        ]);

        return new JsonResponse([
            'baseUrl' => $credentials->baseUrl,
            'accessToken' => $credentials->accessToken,
        ], Response::HTTP_OK);
    }
}
