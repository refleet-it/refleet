<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Query\ListRunnerJobs;

use App\Runner\Runner\Domain\Runner\Exception\RunnerNotFoundException;
use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use App\Runner\Runner\Domain\RunnerJob\Model\RunnerJob;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Entirely self-contained within the Runner context: the project name comes from the
 * job's own payload snapshot (captured by the enqueuing context at creation time) and
 * the owner's title from RunnerJob::ownerLabel() — no cross-context query is needed to
 * render a human-readable list.
 */
#[AsMessageHandler]
final readonly class ListRunnerJobsHandler
{
    public function __construct(
        private RunnerRepositoryInterface $runners,
        private RunnerJobRepositoryInterface $runnerJobs,
    ) {
    }

    /**
     * @return ListResponse<RunnerJobOverview>
     */
    public function __invoke(ListRunnerJobsQuery $query): ListResponse
    {
        $organizationId = OrganizationId::fromString($query->organizationId);
        $runner = $this->runners->findByIdForOrganization(RunnerId::fromString($query->runnerId), $organizationId);

        if (null === $runner) {
            throw new RunnerNotFoundException();
        }

        $pagination = PaginationParameters::fromRequest($query->page, $query->limit);
        $result = $this->runnerJobs->getPaginatedListByClaimedBy($organizationId, $runner->name(), $pagination);

        /* @var ListResponse<RunnerJobOverview> */
        return ListResponse::create(
            items: \array_map($this->toOverview(...), $result->getItems()),
            totalItems: $result->getTotalItems(),
            pagination: $result->getPagination(),
        );
    }

    private function toOverview(RunnerJob $job): RunnerJobOverview
    {
        $project = $job->payload()['project'] ?? [];

        return new RunnerJobOverview(
            id: $job->id()->asString(),
            kind: $job->kind()->value,
            mode: $job->mode()->value,
            status: $job->status()->value,
            ownerId: $job->ownerId()->asString(),
            ownerLabel: $job->ownerLabel(),
            ownerTargetId: $job->ownerTargetId()->asString(),
            projectName: \is_array($project) && \is_string($project['name'] ?? null) ? $project['name'] : 'Unknown',
            attemptCount: $job->attemptCount(),
            resultSummary: $job->resultSummary(),
            errorMessage: $job->errorMessage(),
            createdAt: $job->createdAt()->format('c'),
            claimedAt: $job->claimedAt()?->format('c'),
            completedAt: $job->completedAt()?->format('c'),
        );
    }
}
