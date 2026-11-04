<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Service;

use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabAccessTokenResolver;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabApiClientInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use App\Shared\Domain\Service\MergeRequestPipelineReaderInterface;
use Psr\Log\LoggerInterface;

/**
 * Reads merge request pipelines with the organization's own GitLab credentials. Every
 * failure is swallowed into a missing entry and a log line, per the port's contract, for
 * the same reason as GitLabMergeRequestStateReader.
 */
final readonly class GitLabMergeRequestPipelineReader implements MergeRequestPipelineReaderInterface
{
    public function __construct(
        private GitLabConnectionRepositoryInterface $connections,
        private GitLabApiClientInterface $gitLabApiClient,
        private GitLabAccessTokenResolver $accessTokens,
        private GitLabMergeRequestPipelineNormalizer $normalizer,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function pipelinesFor(string $organizationId, array $iidsByProjectExternalId): array
    {
        if ([] === $iidsByProjectExternalId) {
            return [];
        }

        $connection = $this->connections->findByOrganizationId(OrganizationId::fromString($organizationId));

        if (null === $connection) {
            return [];
        }

        try {
            $accessToken = $this->accessTokens->resolve($connection);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Could not obtain a GitLab access token to read merge request pipelines', [
                'organizationId' => $organizationId,
                'error' => $throwable->getMessage(),
            ]);

            return [];
        }

        $pipelines = [];

        foreach ($iidsByProjectExternalId as $projectExternalId => $iids) {
            foreach ($iids as $iid) {
                try {
                    $mergeRequest = $this->gitLabApiClient->getMergeRequest($connection->baseUrl(), $accessToken, (string) $projectExternalId, $iid);
                    $pipelines[(string) $projectExternalId][$iid] = $this->normalizer->normalize($mergeRequest);
                } catch (\Throwable $throwable) {
                    $this->logger->warning('Could not read a merge request pipeline from GitLab', [
                        'organizationId' => $organizationId,
                        'projectExternalId' => $projectExternalId,
                        'iid' => $iid,
                        'error' => $throwable->getMessage(),
                    ]);
                }
            }
        }

        return $pipelines;
    }
}
