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
        $this->removeGroupWebhook($connection, $accessToken, $webhookUrl);

        try {
            foreach ($this->gitLabApiClient->listGroupProjects($connection->baseUrl(), $accessToken, $connection->groupId()) as $project) {
                $externalId = $project['id'] ?? null;

                if (\is_scalar($externalId)) {
                    $seenExternalIds[] = (string) $externalId;
                    $this->removeProjectWebhook($connection, $accessToken, (string) $externalId, $webhookUrl);
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
     * Unlike the webhook clean-up this is not best-effort: without the label the runner's
     * merge requests would still open, but GitLab would auto-create the label in a random
     * colour (or silently drop it), so the sync is marked failed and the reason shown to
     * the customer.
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
     * Refleet used to register merge request webhooks here and now polls GitLab instead,
     * so a sync tidies away the hooks earlier versions left behind. Best-effort, and
     * deletable once every instance has synced at least once on this version — until then
     * it is what keeps a customer's group from carrying hooks that only ever 404.
     */
    private function removeGroupWebhook(GitLabConnection $connection, string $accessToken, string $webhookUrl): void
    {
        try {
            $this->gitLabApiClient->removeGroupWebhook(
                $connection->baseUrl(),
                $accessToken,
                $connection->groupId(),
                $webhookUrl,
            );
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to remove a stale GitLab group webhook during sync', [
                'organizationId' => $connection->organizationId()->asString(),
                'groupId' => $connection->groupId(),
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @see self::removeGroupWebhook() — the per-project half of the same clean-up
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
