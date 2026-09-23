<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Service;

use App\Organization\GitLabConnection\Domain\Connection\Exception\InvalidGitLabCredentialsException;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabOAuthClientInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabOAuthTokens;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GitLabOAuthClient implements GitLabOAuthClientInterface
{
    public const string CALLBACK_PATH = '/dashboard/settings/gitlab/callback';

    /**
     * `api` is the narrowest scope that lets a runner push a branch and open a merge request
     * on it; GitLab has no MR-only scope. Both tokens are short-lived either way.
     */
    private const string SCOPE = 'api';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(GITLAB_OAUTH_CLIENT_ID)%')]
        private string $clientId,
        #[Autowire('%env(GITLAB_OAUTH_CLIENT_SECRET)%')]
        private string $clientSecret,
        #[Autowire('%env(FRONTEND_URL)%')]
        private string $frontendUrl,
        // A self-managed GitLab serves the same OAuth endpoints under its own host; only the
        // token-paste path worked against one before this was configurable.
        #[Autowire('%env(GITLAB_OAUTH_BASE_URL)%')]
        private string $baseUrl,
    ) {
    }

    #[\Override]
    public function isConfigured(): bool
    {
        return '' !== $this->clientId && '' !== $this->clientSecret;
    }

    #[\Override]
    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    #[\Override]
    public function authorizationUrl(string $state): string
    {
        return $this->baseUrl.'/oauth/authorize?'.\http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
        ]);
    }

    #[\Override]
    public function exchangeCode(string $code): GitLabOAuthTokens
    {
        return $this->requestTokens([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ], 'the authorization code was rejected by GitLab');
    }

    #[\Override]
    public function refresh(string $refreshToken): GitLabOAuthTokens
    {
        return $this->requestTokens([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ], 'GitLab refused to refresh the access token; reconnect GitLab');
    }

    /**
     * @param array<string, string> $grant
     */
    private function requestTokens(array $grant, string $failureReason): GitLabOAuthTokens
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.'/oauth/token', [
                'body' => $grant + [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new InvalidGitLabCredentialsException($failureReason);
            }

            $data = $response->toArray();
        } catch (InvalidGitLabCredentialsException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new InvalidGitLabCredentialsException('the GitLab instance could not be reached');
        }

        $accessToken = $data['access_token'] ?? null;
        $refreshToken = $data['refresh_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? null;

        if (!\is_string($accessToken) || '' === $accessToken || !\is_string($refreshToken) || '' === $refreshToken || !\is_int($expiresIn)) {
            throw new InvalidGitLabCredentialsException('GitLab returned an incomplete token response');
        }

        return new GitLabOAuthTokens(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresAt: (new \DateTimeImmutable())->modify(\sprintf('+%d seconds', $expiresIn)),
        );
    }

    private function redirectUri(): string
    {
        return \rtrim($this->frontendUrl, '/').self::CALLBACK_PATH;
    }
}
