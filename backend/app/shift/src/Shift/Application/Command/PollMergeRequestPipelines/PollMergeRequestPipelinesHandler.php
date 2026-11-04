<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\PollMergeRequestPipelines;

use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Service\MergeRequestPipelineReaderInterface;
use App\Shared\Domain\ValueObject\Id;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\Service\ShiftJobPayloadFactory;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\MergeRequestPipeline;
use App\Shift\Shift\Infrastructure\Bus\RunnerJobRequested\RunnerJobRequestedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Asks GitLab how the pipelines on Refleet's open merge requests went, and queues an agent
 * for the ones that failed. This is the watching half of the stage: nothing runs on a
 * runner and no tokens are spent until a pipeline has actually gone red, because a job is
 * one prompt run to completion and an agent left waiting would hold a runner doing nothing.
 */
#[AsMessageHandler]
final readonly class PollMergeRequestPipelinesHandler
{
    /** A pass costs one GitLab call per merge request, unlike the merge request state poll. */
    private const int BATCH_SIZE = 100;

    /**
     * The internal call to the Organization context has a 5 second timeout and each merge
     * request behind it is a GitLab request, so one big call for a busy organization would
     * time out on every pass and never learn anything.
     */
    private const int MERGE_REQUESTS_PER_READ = 5;

    /** Comfortably shorter than the schedule's interval, so a pass never skips its own due targets. */
    private const string RECHECK_AFTER = '-4 minutes';

    public function __construct(
        private ShiftTargetRepositoryInterface $shiftTargets,
        private ShiftRepositoryInterface $shifts,
        private MergeRequestPipelineReaderInterface $mergeRequestPipelines,
        private ShiftJobPayloadFactory $payloadFactory,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PollMergeRequestPipelinesCommand $command): void
    {
        $due = $this->shiftTargets->findPipelinesToCheck(new \DateTimeImmutable(self::RECHECK_AFTER), self::BATCH_SIZE);

        foreach ($this->groupByOrganization($due) as $organizationId => $targets) {
            foreach (\array_chunk($targets, self::MERGE_REQUESTS_PER_READ) as $chunk) {
                $this->check($organizationId, $chunk);
            }
        }

        $this->shiftTargets->saveAll($due);
    }

    /**
     * @param list<ShiftTarget> $targets
     */
    private function check(string $organizationId, array $targets): void
    {
        $pipelines = $this->read($organizationId, $targets);

        foreach ($targets as $target) {
            // Stamped even without an answer, so a broken connection rotates to the back of the queue.
            $target->recordPipelineChecked();

            $byIid = $pipelines[$target->projectSnapshot()->externalId()] ?? [];
            $reported = $byIid[$target->mergeRequestExternalIid() ?? ''] ?? null;

            // A merge request that left "opened" is the state poll's to settle, not a pipeline to fix.
            if (null === $reported || 'opened' !== $reported['state']) {
                continue;
            }

            $observed = new MergeRequestPipeline(
                headSha: $reported['headSha'],
                pipelineId: $reported['pipelineId'],
                pipelineSha: $reported['pipelineSha'],
                pipelineRef: $reported['pipelineRef'],
                pipelineStatus: $reported['pipelineStatus'],
            );

            if ($target->observePipeline($observed)) {
                $this->requestFix($target, $observed);
            }
        }
    }

    /**
     * @param list<ShiftTarget> $targets
     *
     * @return array<string, array<string, array{state: string, headSha: string, pipelineId: ?string, pipelineSha: ?string, pipelineRef: ?string, pipelineStatus: ?string}>>
     */
    private function read(string $organizationId, array $targets): array
    {
        $iidsByProject = [];

        foreach ($targets as $target) {
            $iid = $target->mergeRequestExternalIid();

            if (null !== $iid) {
                $iidsByProject[$target->projectSnapshot()->externalId()][] = $iid;
            }
        }

        try {
            return $this->mergeRequestPipelines->pipelinesFor($organizationId, $iidsByProject);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Pipeline polling could not reach the GitLab connection', [
                'organizationId' => $organizationId,
                'error' => $throwable->getMessage(),
            ]);

            return [];
        }
    }

    private function requestFix(ShiftTarget $target, MergeRequestPipeline $observed): void
    {
        $shift = $this->shifts->findById($target->shiftId());
        $criteria = $shift?->changeCriteria();

        if (null === $shift || null === $criteria) {
            return;
        }

        $jobId = Id::generate()->asString();
        $target->startPipelineFix($jobId, (string) $observed->pipelineId);

        $this->bus->dispatch(new RunnerJobRequestedMessage(
            jobId: $jobId,
            ownerId: $shift->id()->asString(),
            ownerTargetId: $target->id()->asString(),
            ownerLabel: $shift->title(),
            organizationId: $shift->organizationId()->asString(),
            kind: 'pipeline_fix',
            mode: $criteria->mode()->value,
            payload: $this->payloadFactory->buildPipelineFix($criteria, $target->projectSnapshot(), [
                'branchName' => $target->changeBranchName(),
                'headSha' => $observed->headSha,
                'pipelineId' => $observed->pipelineId,
                'mergeRequestIid' => $target->mergeRequestExternalIid(),
            ]),
            engine: ($criteria->engine() ?? CriteriaEngineEnum::CLAUDE)->value,
        ));

        // Straight away rather than at the end of the pass: a job that is already queued must
        // not be queued again for the same pipeline if something later in the pass throws.
        $this->shiftTargets->save($target);
    }

    /**
     * @param ShiftTarget[] $targets
     *
     * @return array<string, list<ShiftTarget>>
     */
    private function groupByOrganization(array $targets): array
    {
        $grouped = [];

        foreach ($targets as $target) {
            $grouped[$target->organizationId()->asString()][] = $target;
        }

        return $grouped;
    }
}
