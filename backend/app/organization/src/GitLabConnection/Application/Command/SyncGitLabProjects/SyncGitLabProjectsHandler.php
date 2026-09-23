<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Command\SyncGitLabProjects;

use App\Organization\GitLabConnection\Domain\Connection\Exception\GitLabConnectionNotFoundException;
use App\Organization\GitLabConnection\Domain\Connection\Exception\InvalidGitLabCredentialsException;
use App\Organization\GitLabConnection\Domain\Connection\Model\GitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabAccessTokenResolver;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabApiClientInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabLabel;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use App\Shared\Domain\Service\ProjectRegistryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncGitLabProjectsHandler
{
    public function __construct(
        private GitLabConnectionRepositoryInterface $connections,
        private GitLabApiClientInterface $gitLabApiClient,
        private GitLabAccessTokenResolver $accessTokens,
        private ProjectRegistryInterface $projectRegistry,
        private LoggerInterface $logger,
        #[Autowire('%env(APP_URL)%')]
        private string $appUrl,
    ) {
    }

    public function __invoke(SyncGitLabProjectsCommand $command): SyncedGitLabProjects
    {
        $connection = $this->connections->findByOrganizationId(OrganizationId::fromString($command->organizationId));

        if (null === $connection) {
            throw new GitLabConnectionNotFoundException();
        }

        $accessToken = $this->accessTokens->resolve($connection);

        [$syncedCount, $failedCount, $seenExternalIds] = $this->syncProjects($command->organizationId, $connection, $accessToken);
        $labelError = $this->ensureRefleetLabel($connection, $accessToken);

        if (null !== $labelError) {
            $connection->recordSyncFailure($labelError);
        } elseif (0 === $syncedCount && $failedCount > 0) {
            $connection->recordSyncFailure(\sprintf('%d project(s) failed to sync', $failedCount));
        } else {
            $connection->recordSyncSuccess($syncedCount);
        }

        $this->connections->save($connection);

        $archivedCount = $this->projectRegistry->archiveMissing($command->organizationId, $seenExternalIds);

        return new SyncedGitLabProjects(
            syncedCount: $syncedCount,
            failedCount: $failedCount,
            archivedCount: $archivedCount,
            lastSyncedAt: $connection->lastSyncedAt()?->format('c') ?? '',
        );
    }

    /**
     * @return array{0: int, 1: int, 2: list<string>}
     */
    private function syncProjects(string $organizationId, GitLabConnection $connection, string $accessToken): array
    {
        $syncedCount = 0;
        $failedCount = 0;
        $seenExternalIds = [];

        $webhookUrl = \sprintf('%s/api/webhooks/gitlab/%s', \rtrim($this->appUrl, '/'), $organizationId);
        $groupWebhookInPlace = $this->ensureGroupWebhook($connection, $accessToken, $webhookUrl);

        try {
            foreach ($this->gitLabApiClient->listGroupProjects($connection->baseUrl(), $accessToken, $connection->groupId()) as $project) {
                $externalId = $project['id'] ?? null;

                if (\is_scalar($externalId)) {
                    $seenExternalIds[] = (string) $externalId;

                    if ($groupWebhookInPlace) {
                        $this->removeProjectWebhook($connection, $accessToken, (string) $externalId, $webhookUrl);
                    } else {
                        $this->ensureProjectWebhook($connection, $accessToken, (string) $externalId, $webhookUrl);
                    }
                }

                if ($this->registerProject($organizationId, $project)) {
                    ++$syncedCount;
                } else {
                    ++$failedCount;
                }
            }
        } catch (InvalidGitLabCredentialsException $invalidGitLabCredentialsException) {
            $connection->recordSyncFailure($invalidGitLabCredentialsException->getMessage());
            $this->connections->save($connection);

            throw $invalidGitLabCredentialsException;
        }

        return [$syncedCount, $failedCount, $seenExternalIds];
    }

    /**
     * Unlike the webhooks this is not best-effort: without the label the runner's merge
     * requests would still open, but GitLab would auto-create the label in a random colour
     * (or silently drop it), so the sync is marked failed and the reason shown to the customer.
     */
    private function ensureRefleetLabel(GitLabConnection $connection, string $accessToken): ?string
    {
        try {
            $this->gitLabApiClient->ensureGroupLabel(
                $connection->baseUrl(),
                $accessToken,
                $connection->groupId(),
                GitLabLabel::refleet(),
            );
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to ensure the Refleet label in the GitLab group during sync', [
                'organizationId' => $connection->organizationId()->asString(),
                'groupId' => $connection->groupId(),
                'error' => $throwable->getMessage(),
            ]);

            return $throwable->getMessage();
        }

        return null;
    }

    /**
     * One hook on the group covers every project in it and its subgroups, so it is preferred
     * over cluttering each project with its own. Best-effort like the project hooks: GitLab
     * refusing it (Free tier, token below Owner) or being unreachable only means falling back
     * to per-project hooks for this sync.
     */
    private function ensureGroupWebhook(GitLabConnection $connection, string $accessToken, string $webhookUrl): bool
    {
        try {
            $inPlace = $this->gitLabApiClient->ensureGroupWebhook(
                $connection->baseUrl(),
                $accessToken,
                $connection->groupId(),
                $webhookUrl,
                $connection->webhookSecret(),
            );
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to register GitLab group merge request webhook during sync, falling back to project webhooks', [
                'organizationId' => $connection->organizationId()->asString(),
                'groupId' => $connection->groupId(),
                'error' => $throwable->getMessage(),
            ]);

            return false;
        }

        if (!$inPlace) {
            $this->logger->info('GitLab group webhooks are unavailable for this connection, falling back to project webhooks', [
                'organizationId' => $connection->organizationId()->asString(),
                'groupId' => $connection->groupId(),
            ]);
        }

        return $inPlace;
    }

    /**
     * Best-effort: a failure here (insufficient token scope, GitLab tier restrictions,
     * the webhook URL being unreachable from GitLab's side, ...) must not fail the sync
     * that already found and registered the project — it only means merge request status
     * keeps relying on the runner's own report instead of updating instantly.
     */
    private function ensureProjectWebhook(GitLabConnection $connection, string $accessToken, string $externalId, string $webhookUrl): void
    {
        try {
            $this->gitLabApiClient->ensureProjectWebhook(
                $connection->baseUrl(),
                $accessToken,
                $externalId,
                $webhookUrl,
                $connection->webhookSecret(),
            );
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to register GitLab merge request webhook during sync', [
                'organizationId' => $connection->organizationId()->asString(),
                'externalId' => $externalId,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Project hooks left over from before the group hook existed would make every merge
     * request event arrive twice, so they are cleaned up — also best-effort.
     */
    private function removeProjectWebhook(GitLabConnection $connection, string $accessToken, string $externalId, string $webhookUrl): void
    {
        try {
            $this->gitLabApiClient->removeProjectWebhook(
                $connection->baseUrl(),
                $accessToken,
                $externalId,
                $webhookUrl,
            );
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to remove a stale GitLab project webhook during sync', [
                'organizationId' => $connection->organizationId()->asString(),
                'externalId' => $externalId,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @param array<mixed> $project
     */
    private function registerProject(string $organizationId, array $project): bool
    {
        $externalId = $project['id'] ?? null;

        try {
            $name = $project['name'] ?? null;
            $path = $project['path_with_namespace'] ?? null;

            if (!\is_scalar($externalId) || !\is_scalar($name) || !\is_scalar($path)) {
                throw new \RuntimeException('GitLab project response is missing required fields');
            }

            $webUrl = $project['web_url'] ?? null;
            $defaultBranch = $project['default_branch'] ?? null;
            $description = $project['description'] ?? null;

            $this->projectRegistry->register(
                organizationId: $organizationId,
                externalId: (string) $externalId,
                name: (string) $name,
                path: (string) $path,
                webUrl: \is_scalar($webUrl) ? (string) $webUrl : null,
                defaultBranch: \is_scalar($defaultBranch) ? (string) $defaultBranch : null,
                description: \is_scalar($description) && '' !== (string) $description ? (string) $description : null,
            );

            return true;
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to register GitLab project during sync', [
                'organizationId' => $organizationId,
                'externalId' => $externalId,
                'error' => $throwable->getMessage(),
            ]);

            return false;
        }
    }
}
