<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\ReportRunnerJobResult;

use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobKindEnum;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobStatusEnum;
use App\Runner\Runner\Domain\RunnerJob\Exception\RunnerJobNotClaimedByCallerException;
use App\Runner\Runner\Domain\RunnerJob\Exception\RunnerJobNotFoundException;
use App\Runner\Runner\Domain\RunnerJob\Model\RunnerJob;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobId;
use App\Runner\Runner\Infrastructure\Bus\QualificationTargetResultReported\QualificationTargetResultReportedMessage;
use App\Runner\Runner\Infrastructure\Bus\ShiftTargetResultReported\ShiftTargetResultReportedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Idempotent by design: a job that is no longer CLAIMED (already reported, possibly
 * twice by a flaky runner) short-circuits and returns the previously recorded result
 * instead of raising an error — this also naturally makes a late report against an
 * already-cancelled Qualification/Shift a no-op rather than a 500.
 *
 * Runner has no knowledge of Qualification's/Shift's domain — it only records the job
 * outcome on itself, then publishes a message to whichever context enqueued the job
 * (disambiguated by `kind`) so that context can update its own target aggregate and decide
 * whether the parent Qualification/Shift is now finished.
 */
#[AsMessageHandler]
final readonly class ReportRunnerJobResultHandler
{
    public function __construct(
        private RunnerJobRepositoryInterface $runnerJobs,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ReportRunnerJobResultCommand $command): ReportedRunnerJobResult
    {
        $job = $this->findOwnedRunnerJob($command->jobId, $command->organizationId);

        if (RunnerJobStatusEnum::CLAIMED !== $job->status()) {
            return $this->toResult($job);
        }

        if ($job->claimedBy() !== $command->runnerId) {
            throw new RunnerJobNotClaimedByCallerException();
        }

        $isSuccess = 'success' === $command->outcome;

        if ($isSuccess) {
            $job->reportSuccess($command->summary, $command->details ?? []);
        } else {
            $job->reportFailure($command->errorMessage ?? $command->summary);
        }

        $this->runnerJobs->save($job);

        $this->bus->dispatch($this->buildOwnerResultMessage($job, $command, $isSuccess));

        return $this->toResult($job);
    }

    private function buildOwnerResultMessage(RunnerJob $job, ReportRunnerJobResultCommand $command, bool $isSuccess): object
    {
        return RunnerJobKindEnum::QUALIFICATION === $job->kind()
            ? new QualificationTargetResultReportedMessage(
                qualificationId: $job->ownerId()->asString(),
                targetId: $job->ownerTargetId()->asString(),
                organizationId: $job->organizationId()->asString(),
                success: $isSuccess,
                summary: $command->summary,
                runnerName: $command->runnerId,
                errorMessage: $command->errorMessage,
                score: $command->score,
            )
            : new ShiftTargetResultReportedMessage(
                shiftId: $job->ownerId()->asString(),
                targetId: $job->ownerTargetId()->asString(),
                organizationId: $job->organizationId()->asString(),
                success: $isSuccess,
                summary: $command->summary,
                runnerName: $command->runnerId,
                errorMessage: $command->errorMessage,
                branchName: $command->branchName,
                mergeRequestUrl: $command->mergeRequestUrl,
                mergeRequestIid: $command->mergeRequestIid,
            );
    }

    private function findOwnedRunnerJob(string $jobId, string $organizationId): RunnerJob
    {
        $job = $this->runnerJobs->findById(RunnerJobId::fromString($jobId));

        if (null === $job || !$job->organizationId()->equals(OrganizationId::fromString($organizationId))) {
            throw new RunnerJobNotFoundException();
        }

        return $job;
    }

    private function toResult(RunnerJob $job): ReportedRunnerJobResult
    {
        return new ReportedRunnerJobResult(
            jobId: $job->id()->asString(),
            status: $job->status()->value,
            resultSummary: $job->resultSummary(),
            resultDetails: $job->resultDetails(),
            errorMessage: $job->errorMessage(),
            completedAt: $job->completedAt()?->format('c'),
        );
    }
}
