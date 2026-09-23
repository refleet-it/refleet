<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Query\ListRunners;

use App\Runner\Runner\Domain\Runner\Model\Runner;
use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\Service\LatestRunnerVersionProviderInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use App\Shared\Domain\ValueObject\ListParameters;
use App\Shared\Domain\ValueObject\ListResponse;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListRunnersHandler
{
    private const array ALLOWED_SORT_FIELDS = ['name', 'createdAt', 'lastSeenAt'];

    public function __construct(
        private RunnerRepositoryInterface $runners,
        private RunnerJobRepositoryInterface $runnerJobs,
        private LatestRunnerVersionProviderInterface $latestVersions,
    ) {
    }

    /**
     * @return ListResponse<RunnerOverview>
     */
    public function __invoke(ListRunnersQuery $query): ListResponse
    {
        $organizationId = OrganizationId::fromString($query->organizationId);
        $now = new \DateTimeImmutable();

        $parameters = ListParameters::fromRequest(
            page: $query->page,
            limit: $query->limit,
            sortBy: $query->sortBy,
            sortDirection: $query->sortDirection,
            allowedSortFields: self::ALLOWED_SORT_FIELDS,
        );

        $result = $this->runners->getPaginatedList($organizationId, $parameters->getPagination(), $parameters->getSorting(), $query->archived);

        // Batched into a single query rather than per-runner, to avoid N+1 lookups.
        $names = \array_map(static fn (Runner $runner): string => $runner->name(), $result->getItems());
        $workingNames = $this->runnerJobs->findNamesWithActiveClaimedJob($organizationId, $names);
        $latestVersion = $this->latestVersions->latestVersion();

        /* @var ListResponse<RunnerOverview> */
        return ListResponse::create(
            items: \array_map(
                fn (Runner $runner): RunnerOverview => $this->toOverview($runner, $now, \in_array($runner->name(), $workingNames, true), $latestVersion),
                $result->getItems(),
            ),
            totalItems: $result->getTotalItems(),
            pagination: $result->getPagination(),
        );
    }

    private function toOverview(Runner $runner, \DateTimeImmutable $now, bool $hasClaimedJob, ?string $latestVersion): RunnerOverview
    {
        return new RunnerOverview(
            id: $runner->id()->asString(),
            name: $runner->name(),
            status: $runner->effectiveStatus($now, $hasClaimedJob)->value,
            lastSeenAt: $runner->lastSeenAt()?->format('c'),
            createdAt: $runner->createdAt()->format('c'),
            archivedAt: $runner->archivedAt()?->format('c'),
            supportedEngines: $runner->supportedEngines(),
            supportedModels: $runner->supportedModels(),
            usage: $runner->usage(),
            version: $runner->version(),
            latestVersion: $latestVersion,
            updateAvailable: $runner->isBehind($latestVersion),
            updateRequestedAt: $runner->updateRequestedAt()?->format('c'),
        );
    }
}
