<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Query\GetRunner;

use App\Runner\Runner\Domain\Runner\Exception\RunnerNotFoundException;
use App\Runner\Runner\Domain\Runner\Model\Runner;
use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\Service\LatestRunnerVersionProviderInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetRunnerHandler
{
    public function __construct(
        private RunnerRepositoryInterface $runners,
        private RunnerJobRepositoryInterface $runnerJobs,
        private LatestRunnerVersionProviderInterface $latestVersions,
    ) {
    }

    public function __invoke(GetRunnerQuery $query): RunnerDetail
    {
        $organizationId = OrganizationId::fromString($query->organizationId);

        $runner = $this->runners->findByIdForOrganization(
            RunnerId::fromString($query->runnerId),
            $organizationId,
        );

        if (null === $runner) {
            throw new RunnerNotFoundException();
        }

        $hasClaimedJob = [] !== $this->runnerJobs->findNamesWithActiveClaimedJob($organizationId, [$runner->name()]);

        return $this->toDetail($runner, new \DateTimeImmutable(), $hasClaimedJob, $this->latestVersions->latestVersion());
    }

    private function toDetail(Runner $runner, \DateTimeImmutable $now, bool $hasClaimedJob, ?string $latestVersion): RunnerDetail
    {
        return new RunnerDetail(
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
