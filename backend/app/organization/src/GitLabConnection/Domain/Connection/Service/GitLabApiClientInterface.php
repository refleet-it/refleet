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
     * Registers a group-level webhook for merge request events across every project in the
     * group and its subgroups, unless one already points at $webhookUrl. Returns false when
     * GitLab refuses the group hook outright — group webhooks are Premium/Ultimate only and
     * need the Owner role — so the caller can fall back to project-level hooks.
     */
    public function ensureGroupWebhook(
        string $baseUrl,
        string $accessToken,
        string $groupId,
        string $webhookUrl,
        string $secretToken,
    ): bool;

    /**
     * Registers a project-level webhook for merge request events, unless one already
     * points at $webhookUrl. The fallback for GitLab tiers without group webhooks.
     */
    public function ensureProjectWebhook(
        string $baseUrl,
        string $accessToken,
        string $externalProjectId,
        string $webhookUrl,
        string $secretToken,
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
