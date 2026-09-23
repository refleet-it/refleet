<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\EnqueueRunnerJob;

use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobKindEnum;
use App\Runner\Runner\Domain\RunnerJob\Model\RunnerJob;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerTargetId;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EnqueueRunnerJobHandler
{
    public function __construct(
        private RunnerJobRepositoryInterface $runnerJobs,
    ) {
    }

    public function __invoke(EnqueueRunnerJobCommand $command): EnqueuedRunnerJob
    {
        $job = RunnerJob::enqueue(
            id: RunnerJobId::fromString($command->jobId),
            ownerId: RunnerJobOwnerId::fromString($command->ownerId),
            ownerTargetId: RunnerJobOwnerTargetId::fromString($command->ownerTargetId),
            organizationId: OrganizationId::fromString($command->organizationId),
            kind: RunnerJobKindEnum::from($command->kind),
            ownerLabel: $command->ownerLabel,
            mode: CriteriaModeEnum::from($command->mode),
            payload: $command->payload,
            engine: null !== $command->engine ? CriteriaEngineEnum::from($command->engine) : null,
        );

        $this->runnerJobs->save($job);

        return new EnqueuedRunnerJob(jobId: $job->id()->asString());
    }
}
