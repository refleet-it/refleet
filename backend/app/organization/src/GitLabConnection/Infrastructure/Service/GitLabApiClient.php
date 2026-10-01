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
 * Client for interacting with the GitLab REST API (v4) to resolve a group, list its projects, read merge request states and manage labels.
 */
final readonly class GitLabApiClient implements GitLabApiClientInterface
{
    /** GitLab caps per_page at 100; asking for more is silently truncated, so paging is required either way. */
    private const int PROJECTS_PER_PAGE = 100;

    /**
     * How many iids go into one merge request query. Well under the per_page cap, and it
     * keeps the hand-built query string short enough for whatever proxy sits in front of
     * a self-managed instance.
     */
    private const int MERGE_REQUEST_IIDS_PER_REQUEST = 50;

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

    #[\Override]
    public function listMergeRequestStates(string $baseUrl, string $accessToken, string $externalProjectId, array $iids): array
    {
        $states = [];

        foreach (\array_chunk($iids, self::MERGE_REQUEST_IIDS_PER_REQUEST) as $chunk) {
            foreach ($this->fetchMergeRequests($baseUrl, $accessToken, $externalProjectId, $chunk) as $mergeRequest) {
                if (!\is_array($mergeRequest) || !\is_scalar($mergeRequest['iid'] ?? null) || !\is_string($mergeRequest['state'] ?? null)) {
                    continue;
                }

                $states[(string) $mergeRequest['iid']] = $mergeRequest['state'];
            }
        }

        return $states;
    }

    /**
     * Group hooks are Premium/Ultimate only and need the Owner role, so a 403/404 here
     * means there was never a group hook to remove rather than a failure.
     */
    #[\Override]
    public function removeGroupWebhook(
        string $baseUrl,
        string $accessToken,
        string $groupId,
        string $webhookUrl,
    ): void {
        $hooksPath = '/groups/'.\rawurlencode($groupId).'/hooks';

        $response = $this->requestHooks('GET', $baseUrl, $accessToken, $hooksPath, 'listing');

        if (\in_array($response->getStatusCode(), [403, 404], true)) {
            return;
        }

        $this->deleteListedHooks($baseUrl, $accessToken, $hooksPath, $webhookUrl, $this->decodeOrFail($response, self::HOOK_OWNER_NOT_FOUND));
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

        $this->deleteListedHooks($baseUrl, $accessToken, $hooksPath, $webhookUrl, $hooks);
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
     * GitLab expects the iids as repeated `iids[]` parameters. PHP's own query
     * serialization would send `iids[0]=…`, which Rails parses as a hash and the endpoint
     * then rejects, so the query string is built by hand.
     *
     * @param list<string> $iids
     *
     * @return array<mixed>
     */
    private function fetchMergeRequests(string $baseUrl, string $accessToken, string $externalProjectId, array $iids): array
    {
        $url = $this->endpoint($baseUrl, '/projects/'.\rawurlencode($externalProjectId).'/merge_requests')
            .'?per_page='.self::MERGE_REQUEST_IIDS_PER_REQUEST
            .'&'.\implode('&', \array_map(static fn (string $iid): string => 'iids%5B%5D='.\rawurlencode($iid), $iids));

        try {
            $response = $this->httpClient->request('GET', $url, ['headers' => ['Authorization' => 'Bearer '.$accessToken]]);

            return $this->decodeOrFail($response, \sprintf('project "%s" was not found', $externalProjectId));
        } catch (InvalidGitLabCredentialsException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new InvalidGitLabCredentialsException('the GitLab instance could not be reached while reading merge requests');
        }
    }

    /**
     * @param array<mixed> $hooks
     */
    private function deleteListedHooks(string $baseUrl, string $accessToken, string $hooksPath, string $webhookUrl, array $hooks): void
    {
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
