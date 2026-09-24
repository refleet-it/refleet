<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Domain\Connection\Service;

use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabLabel;

interface GitLabApiClientInterface
{
    /**
     * @return array{id: string, name: string, fullPath: string}
     */
    public function resolveGroup(string $baseUrl, string $accessToken, string $groupPath): array;

    /**
     * @return iterable<array<mixed>>
     */
    public function listGroupProjects(string $baseUrl, string $accessToken, string $groupId): iterable;

    /**
     * Reads the state of the given merge requests in one project. GitLab takes a list of
     * iids per request, so a project with a dozen open merge requests still costs a
     * single call.
     *
     * @param list<string> $iids
     *
     * @return array<string, string> iid => state, one of "opened", "closed", "locked" or
     *                               "merged"; an iid GitLab does not return is absent
     */
    public function listMergeRequestStates(string $baseUrl, string $accessToken, string $externalProjectId, array $iids): array;

    /**
     * Deletes every group-level webhook pointing at $webhookUrl; a no-op when there is
     * none, and when GitLab refuses group hooks outright (Premium/Ultimate only).
     */
    public function removeGroupWebhook(
        string $baseUrl,
        string $accessToken,
        string $groupId,
        string $webhookUrl,
    ): void;

    /**
     * Deletes every project-level webhook pointing at $webhookUrl; a no-op when there is none.
     */
    public function removeProjectWebhook(
        string $baseUrl,
        string $accessToken,
        string $externalProjectId,
        string $webhookUrl,
    ): void;

    /**
     * Creates $label in the group, or brings an existing label of that name back to the
     * expected colour and description. Throws InsufficientGitLabPermissionsException when
     * the token's role in the group is too low to manage labels (Reporter is the minimum).
     */
    public function ensureGroupLabel(
        string $baseUrl,
        string $accessToken,
        string $groupId,
        GitLabLabel $label,
    ): void;
}
