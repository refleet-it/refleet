<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\PollOpenMergeRequests;

use App\Shared\Domain\Service\MergeRequestStateReaderInterface;
use App\Shift\Shift\Domain\Shift\Service\ShiftCompletion;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Exception\InvalidShiftTargetStateTransitionException;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Asks GitLab what happened to the merge requests Refleet is still waiting on, and
 * settles the targets whose merge request has since been merged or closed.
 *
 * This is how a shift finishes. Refleet used to be told by a GitLab webhook, which meant
 * every customer needed a hook Refleet could register (group hooks are Premium and need
 * Owner) reaching a URL their GitLab could actually resolve — and when any of that did
 * not hold, the target sat in MERGE_REQUEST_OPEN forever and its shift never completed.
 * Asking GitLab ourselves needs nothing beyond the token the connection already has.
 */
#[AsMessageHandler]
final readonly class PollOpenMergeRequestsHandler
{
    /**
     * A pass costs one GitLab call per project it touches, so this caps how long a pass
     * runs rather than how many calls it makes. Whatever does not fit is simply first in
     * line next time — the queue is ordered by least recently checked.
     */
    private const int BATCH_SIZE = 250;

    /** Comfortably shorter than the schedule's interval, so a pass never skips its own due targets. */
    private const string RECHECK_AFTER = '-4 minutes';

    public function __construct(
        private ShiftTargetRepositoryInterface $shiftTargets,
        private MergeRequestStateReaderInterface $mergeRequestStates,
        private ShiftCompletion $shiftCompletion,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PollOpenMergeRequestsCommand $command): void
    {
        $due = $this->shiftTargets->findOpenMergeRequestsToCheck(new \DateTimeImmutable(self::RECHECK_AFTER), self::BATCH_SIZE);

        if ([] === $due) {
            return;
        }

        /** @var array<string, ShiftId> $settledShifts */
        $settledShifts = [];

        foreach ($this->groupByOrganization($due) as $organizationId => $targets) {
            foreach ($this->settle($organizationId, $targets) as $shiftId) {
                $settledShifts[$shiftId->asString()] = $shiftId;
            }
        }

        $this->shiftTargets->saveAll($due);

        foreach ($settledShifts as $shiftId) {
            $this->shiftCompletion->completeIfFinished($shiftId);
        }

        if ([] !== $settledShifts) {
            $this->logger->info('Merge request polling settled targets', [
                'checkedTargets' => \count($due),
                'settledShifts' => \count($settledShifts),
            ]);
        }
    }

    /**
     * @param list<ShiftTarget> $targets
     *
     * @return list<ShiftId> the shifts that had at least one target settled
     */
    private function settle(string $organizationId, array $targets): array
    {
        /** @var array<string, array<string, list<ShiftTarget>>> $byProjectAndIid */
        $byProjectAndIid = [];
        /** @var array<string, list<string>> $iidsByProject */
        $iidsByProject = [];

        foreach ($targets as $target) {
            $iid = $this->iidOf($target);

            if (null === $iid) {
                continue;
            }

            $projectExternalId = $target->projectSnapshot()->externalId();
            $byProjectAndIid[$projectExternalId][$iid][] = $target;
            $iidsByProject[$projectExternalId][] = $iid;
        }

        $states = $this->readStates($organizationId, \array_map(
            static fn (array $iids): array => \array_values(\array_unique($iids)),
            $iidsByProject,
        ));

        $settled = [];

        foreach ($states as $projectExternalId => $byIid) {
            foreach ($byIid as $iid => $state) {
                foreach ($byProjectAndIid[$projectExternalId][$iid] ?? [] as $target) {
                    if ($this->apply($target, $state)) {
                        $settled[] = $target->shiftId();
                    }
                }
            }
        }

        // Stamped even for the ones GitLab said nothing about, so an organization whose
        // connection is broken rotates to the back of the queue instead of filling every pass.
        foreach ($targets as $target) {
            $target->recordMergeRequestChecked();
        }

        return $settled;
    }

    /**
     * @param array<string, list<string>> $iidsByProject
     *
     * @return array<string, array<string, string>>
     */
    private function readStates(string $organizationId, array $iidsByProject): array
    {
        try {
            return $this->mergeRequestStates->statesFor($organizationId, $iidsByProject);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Merge request polling could not reach the GitLab connection', [
                'organizationId' => $organizationId,
                'error' => $throwable->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * GitLab's own vocabulary is "opened", "closed", "locked" and "merged". A merge
     * request still open, temporarily locked, or one GitLab did not answer for at all
     * (deleted, or the token lost sight of the project) leaves its target alone: settling
     * a target on GitLab's silence would strand work that is still live.
     */
    private function apply(ShiftTarget $target, string $state): bool
    {
        if (!\in_array($state, ['merged', 'closed'], true)) {
            return false;
        }

        try {
            if ('merged' === $state) {
                $target->recordMergeRequestMerged();
            } else {
                $target->recordMergeRequestClosed();
            }
        } catch (InvalidShiftTargetStateTransitionException) {
            // The target left MERGE_REQUEST_OPEN between the query and now — someone else settled it.
            return false;
        }

        return true;
    }

    /**
     * Targets created before the runner started reporting the iid have only the merge
     * request URL, whose last segment is that same iid.
     */
    private function iidOf(ShiftTarget $target): ?string
    {
        $iid = $target->mergeRequestExternalIid();

        if (null !== $iid && '' !== $iid) {
            return $iid;
        }

        $url = $target->mergeRequestUrl();

        if (null === $url || 1 !== \preg_match('#/merge_requests/(\d+)#', $url, $matches)) {
            return null;
        }

        return $matches[1];
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
