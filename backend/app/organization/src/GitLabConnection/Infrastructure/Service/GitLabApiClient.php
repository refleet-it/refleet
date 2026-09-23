<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Service;

use App\Organization\GitLabConnection\Domain\Connection\Exception\InsufficientGitLabPermissionsException;
use App\Organization\GitLabConnection\Domain\Connection\Exception\InvalidGitLabCredentialsException;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabApiClientInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabLabel;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client for interacting with the GitLab REST API (v4) to resolve a group, list its projects and manage webhooks and labels.
 */
final readonly class GitLabApiClient implements GitLabApiClientInterface
{
    /** GitLab caps per_page at 100; asking for more is silently truncated, so paging is required either way. */
    private const int PROJECTS_PER_PAGE = 100;

    private const string HOOK_OWNER_NOT_FOUND = 'the webhook target was not found';

    private const string LABEL_GROUP_NOT_FOUND = 'the group was not found while managing labels';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    #[\Override]
    public function resolveGroup(string $baseUrl, string $accessToken, string $groupPath): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                $this->endpoint($baseUrl, '/groups/'.\rawurlencode($groupPath)),
                ['headers' => ['Authorization' => 'Bearer '.$accessToken]],
            );

            $data = $this->decodeOrFail($response, \sprintf('group "%s" was not found', $groupPath));
        } catch (InvalidGitLabCredentialsException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new InvalidGitLabCredentialsException('the GitLab instance could not be reached');
        }

        return [
            'id' => $this->requireScalarField($data, 'id'),
            'name' => $this->requireScalarField($data, 'name'),
            'fullPath' => $this->requireScalarField($data, 'full_path'),
        ];
    }

    #[\Override]
    public function listGroupProjects(string $baseUrl, string $accessToken, string $groupId): iterable
    {
        $page = 1;

        while (null !== $page) {
            try {
                $response = $this->httpClient->request(
                    'GET',
                    $this->endpoint($baseUrl, '/groups/'.$groupId.'/projects'),
                    [
                        'headers' => ['Authorization' => 'Bearer '.$accessToken],
                        'query' => [
                            'include_subgroups' => 'true',
                            'archived' => 'false',
                            'per_page' => self::PROJECTS_PER_PAGE,
                            'page' => $page,
                        ],
                    ],
                );

                $projects = $this->decodeOrFail($response, \sprintf('group "%s" was not found', $groupId));
                $nextPage = $response->getHeaders()['x-next-page'][0] ?? '';
            } catch (InvalidGitLabCredentialsException $exception) {
                throw $exception;
            } catch (\Throwable) {
                throw new InvalidGitLabCredentialsException('the GitLab instance could not be reached while listing projects');
            }

            foreach ($projects as $project) {
                $normalizedProject = $this->normalizeProject($project);

                if (null !== $normalizedProject) {
                    yield $normalizedProject;
                }
            }

            $page = '' !== $nextPage ? (int) $nextPage : null;
        }
    }

    /**
     * GitLab answers 404 for group hooks on tiers without the feature and 403 when the token's
     * role in the group is too low to manage them; both mean "use project hooks instead".
     */
    #[\Override]
    public function ensureGroupWebhook(
        string $baseUrl,
        string $accessToken,
        string $groupId,
        string $webhookUrl,
        string $secretToken,
    ): bool {
        $hooksPath = '/groups/'.\rawurlencode($groupId).'/hooks';

        $response = $this->requestHooks('GET', $baseUrl, $accessToken, $hooksPath, 'listing');

        if ($this->isGroupWebhookRefused($response)) {
            return false;
        }

        if ($this->webhookIsListed($this->decodeOrFail($response, self::HOOK_OWNER_NOT_FOUND), $webhookUrl)) {
            return true;
        }

        $response = $this->requestHooks('POST', $baseUrl, $accessToken, $hooksPath, 'registering', $this->webhookPayload($webhookUrl, $secretToken));

        if ($this->isGroupWebhookRefused($response)) {
            return false;
        }

        $this->decodeOrFail($response, self::HOOK_OWNER_NOT_FOUND, 201);

        return true;
    }

    #[\Override]
    public function ensureProjectWebhook(
        string $baseUrl,
        string $accessToken,
        string $externalProjectId,
        string $webhookUrl,
        string $secretToken,
    ): void {
        $hooksPath = '/projects/'.\rawurlencode($externalProjectId).'/hooks';

        $hooks = $this->decodeOrFail($this->requestHooks('GET', $baseUrl, $accessToken, $hooksPath, 'listing'), self::HOOK_OWNER_NOT_FOUND);

        if ($this->webhookIsListed($hooks, $webhookUrl)) {
            return;
        }

        $response = $this->requestHooks('POST', $baseUrl, $accessToken, $hooksPath, 'registering', $this->webhookPayload($webhookUrl, $secretToken));

        $this->decodeOrFail($response, self::HOOK_OWNER_NOT_FOUND, 201);
    }

    #[\Override]
    public function removeProjectWebhook(
        string $baseUrl,
        string $accessToken,
        string $externalProjectId,
        string $webhookUrl,
    ): void {
        $hooksPath = '/projects/'.\rawurlencode($externalProjectId).'/hooks';

        $hooks = $this->decodeOrFail($this->requestHooks('GET', $baseUrl, $accessToken, $hooksPath, 'listing'), self::HOOK_OWNER_NOT_FOUND);

        foreach ($hooks as $hook) {
            if (!\is_array($hook) || ($hook['url'] ?? null) !== $webhookUrl || !\is_scalar($hook['id'] ?? null)) {
                continue;
            }

            $response = $this->requestHooks('DELETE', $baseUrl, $accessToken, $hooksPath.'/'.\rawurlencode((string) $hook['id']), 'removing');
            $statusCode = $response->getStatusCode();

            if (!\in_array($statusCode, [204, 404], true)) {
                $this->decodeOrFail($response, self::HOOK_OWNER_NOT_FOUND, 204);
            }
        }
    }

    /**
     * A 403 here is the one permission failure worth telling the customer about by name:
     * listing projects works with any role, but labels need Reporter or above.
     */
    #[\Override]
    public function ensureGroupLabel(
        string $baseUrl,
        string $accessToken,
        string $groupId,
        GitLabLabel $label,
    ): void {
        $labelsPath = '/groups/'.\rawurlencode($groupId).'/labels';

        $response = $this->requestLabels('GET', $baseUrl, $accessToken, $labelsPath, 'listing', ['search' => $label->name, 'per_page' => 100]);
        $existing = $this->findLabel($this->decodeLabelsOrFail($response, $groupId), $label->name);

        if (null === $existing) {
            $response = $this->requestLabels('POST', $baseUrl, $accessToken, $labelsPath, 'creating', null, [
                'name' => $label->name,
                'color' => $label->color,
                'description' => $label->description,
            ]);
            $this->decodeLabelsOrFail($response, $groupId, 201);

            return;
        }

        if ($label->matches($existing['color'], $existing['description'])) {
            return;
        }

        $response = $this->requestLabels('PUT', $baseUrl, $accessToken, $labelsPath.'/'.\rawurlencode($existing['id']), 'updating', null, [
            'color' => $label->color,
            'description' => $label->description,
        ]);
        $this->decodeLabelsOrFail($response, $groupId);
    }

    /**
     * @param array<string, scalar>|null $query
     * @param array<string, mixed>|null  $json
     */
    private function requestLabels(string $method, string $baseUrl, string $accessToken, string $path, string $action, ?array $query = null, ?array $json = null): ResponseInterface
    {
        $options = ['headers' => ['Authorization' => 'Bearer '.$accessToken]];

        if (null !== $query) {
            $options['query'] = $query;
        }

        if (null !== $json) {
            $options['json'] = $json;
        }

        try {
            $response = $this->httpClient->request($method, $this->endpoint($baseUrl, $path), $options);
            $response->getStatusCode();
        } catch (\Throwable) {
            throw new InvalidGitLabCredentialsException(\sprintf('the GitLab instance could not be reached while %s the label', $action));
        }

        return $response;
    }

    /**
     * @return array<mixed>
     */
    private function decodeLabelsOrFail(ResponseInterface $response, string $groupId, int $expectedStatusCode = 200): array
    {
        if (403 === $response->getStatusCode()) {
            throw new InsufficientGitLabPermissionsException(\sprintf('managing labels in group "%s" needs at least the Reporter role', $groupId));
        }

        return $this->decodeOrFail($response, self::LABEL_GROUP_NOT_FOUND, $expectedStatusCode);
    }

    /**
     * GitLab's label search is a substring match, so the exact title still has to be picked out.
     *
     * @param array<mixed> $labels
     *
     * @return array{id: string, color: string, description: string}|null
     */
    private function findLabel(array $labels, string $name): ?array
    {
        foreach ($labels as $label) {
            if (!\is_array($label) || ($label['name'] ?? null) !== $name || !\is_scalar($label['id'] ?? null)) {
                continue;
            }

            $color = $label['color'] ?? '';
            $description = $label['description'] ?? '';

            return [
                'id' => (string) $label['id'],
                'color' => \is_scalar($color) ? (string) $color : '',
                'description' => \is_scalar($description) ? (string) $description : '',
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $json
     */
    private function requestHooks(string $method, string $baseUrl, string $accessToken, string $path, string $action, ?array $json = null): ResponseInterface
    {
        $options = ['headers' => ['Authorization' => 'Bearer '.$accessToken]];

        if (null !== $json) {
            $options['json'] = $json;
        }

        try {
            $response = $this->httpClient->request($method, $this->endpoint($baseUrl, $path), $options);
            $response->getStatusCode();
        } catch (\Throwable) {
            throw new InvalidGitLabCredentialsException(\sprintf('the GitLab instance could not be reached while %s the webhook', $action));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookPayload(string $webhookUrl, string $secretToken): array
    {
        return [
            'url' => $webhookUrl,
            'token' => $secretToken,
            'merge_requests_events' => true,
            'push_events' => false,
            'enable_ssl_verification' => \str_starts_with($webhookUrl, 'https://'),
        ];
    }

    /**
     * @param array<mixed> $hooks
     */
    private function webhookIsListed(array $hooks, string $webhookUrl): bool
    {
        return \array_any($hooks, static fn ($hook) => \is_array($hook) && ($hook['url'] ?? null) === $webhookUrl);
    }

    private function isGroupWebhookRefused(ResponseInterface $response): bool
    {
        return \in_array($response->getStatusCode(), [403, 404], true);
    }

    /**
     * @return array<mixed>|null
     */
    private function normalizeProject(mixed $project): ?array
    {
        return \is_array($project) ? $project : null;
    }

    /**
     * @param array<mixed> $data
     */
    private function requireScalarField(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!\is_scalar($value)) {
            throw new InvalidGitLabCredentialsException(\sprintf('unexpected response shape from GitLab: missing or invalid "%s"', $key));
        }

        return (string) $value;
    }

    /**
     * @return array<mixed>
     */
    private function decodeOrFail(ResponseInterface $response, string $notFoundMessage, int $expectedStatusCode = 200): array
    {
        $statusCode = $response->getStatusCode();

        if (401 === $statusCode || 403 === $statusCode) {
            throw new InvalidGitLabCredentialsException('the access token was rejected');
        }

        if (404 === $statusCode) {
            throw new InvalidGitLabCredentialsException($notFoundMessage);
        }

        if ($expectedStatusCode !== $statusCode) {
            throw new InvalidGitLabCredentialsException(\sprintf('GitLab responded with status %d', $statusCode));
        }

        return $response->toArray();
    }

    private function endpoint(string $baseUrl, string $path): string
    {
        return \rtrim($baseUrl, '/').'/api/v4'.$path;
    }
}
