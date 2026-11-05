<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\ShiftTarget\Model;

use App\Shared\Domain\Event\AggregateRoot;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\MergeRequestStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Enum\PipelineFixOutcomeEnum;
use App\Shift\Shift\Domain\ShiftTarget\Enum\PipelineStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Exception\InvalidShiftTargetStateTransitionException;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\MergeRequestPipeline;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ProjectId;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A deliberately separate aggregate root from Shift: with many targets updated
 * concurrently by runner callbacks, keeping them as a collection on a single Shift
 * aggregate would create lock/optimistic-locking contention. Does not record domain
 * events (bulk creation of dozens of targets would be noise on the bus).
 *
 * @SuppressWarnings("PHPMD.TooManyFields") Tracks independent sub-lifecycles
 * (change, merge request, pipeline) as flattened columns - no Doctrine Embeddables in this
 * codebase - plus one state transition method per lifecycle step.
 * @SuppressWarnings("PHPMD.TooManyMethods") The same cause seen from the other side:
 * every flattened column needs an accessor, and PHPMD counts those as real methods
 * because they carry no "get" or "set" prefix.
 * @SuppressWarnings("PHPMD.TooManyPublicMethods") Same cause as TooManyMethods.
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity") Each sub-lifecycle's transitions and
 * guards live here because this is the aggregate they have to stay consistent within.
 */
#[ORM\Entity]
#[ORM\Table(name: 'shift_targets', schema: 'shift')]
#[ORM\Index(name: 'idx_shift_targets_shift_id_status', columns: ['shift_id', 'status'])]
#[ORM\Index(name: 'idx_shift_targets_organization_id', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_shift_targets_status_mr_checked_at', columns: ['status', 'merge_request_checked_at'])]
#[ORM\Index(name: 'idx_shift_targets_pipeline_checked_at', columns: ['pipeline_status', 'pipeline_checked_at'])]
#[ORM\UniqueConstraint(name: 'uniq_shift_targets_shift_project', columns: ['shift_id', 'project_id'])]
class ShiftTarget extends AggregateRoot
{
    /** Each attempt is an agent run and a commit on someone's merge request, so this stays low. */
    public const int MAX_PIPELINE_FIXES = 3;

    /** How long a merge request may go without a pipeline before the project is taken to have no CI. */
    private const string NO_PIPELINE_AFTER = '-1 hour';

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(name: 'shift_id', type: Types::GUID)]
    private string $shiftId;

    #[ORM\Column(name: 'organization_id', type: Types::GUID)]
    private string $organizationId;

    #[ORM\Column(name: 'project_id', type: Types::GUID)]
    private string $projectId;

    #[ORM\Column(name: 'project_snapshot_external_id', type: Types::STRING, length: 64)]
    private string $projectSnapshotExternalId;

    #[ORM\Column(name: 'project_snapshot_path', type: Types::STRING, length: 255)]
    private string $projectSnapshotPath;

    #[ORM\Column(name: 'project_snapshot_name', type: Types::STRING, length: 255)]
    private string $projectSnapshotName;

    #[ORM\Column(name: 'project_snapshot_default_branch', type: Types::STRING, length: 100, nullable: true)]
    private ?string $projectSnapshotDefaultBranch = null;

    #[ORM\Column(type: Types::STRING, enumType: ShiftTargetStatusEnum::class)]
    private ShiftTargetStatusEnum $status = ShiftTargetStatusEnum::PENDING_CHANGE;

    #[ORM\Column(name: 'change_summary', type: Types::TEXT, nullable: true)]
    private ?string $changeSummary = null;

    #[ORM\Column(name: 'change_branch_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $changeBranchName = null;

    #[ORM\Column(name: 'runner_job_id', type: Types::GUID, nullable: true)]
    private ?string $runnerJobId = null;

    #[ORM\Column(name: 'runner_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $runnerName = null;

    #[ORM\Column(name: 'change_started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $changeStartedAt = null;

    #[ORM\Column(name: 'change_completed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $changeCompletedAt = null;

    #[ORM\Column(name: 'merge_request_url', type: Types::STRING, length: 500, nullable: true)]
    private ?string $mergeRequestUrl = null;

    #[ORM\Column(name: 'merge_request_external_iid', type: Types::STRING, length: 64, nullable: true)]
    private ?string $mergeRequestExternalIid = null;

    #[ORM\Column(name: 'merge_request_status', type: Types::STRING, enumType: MergeRequestStatusEnum::class)]
    private MergeRequestStatusEnum $mergeRequestStatus = MergeRequestStatusEnum::NONE;

    #[ORM\Column(name: 'merge_request_opened_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $mergeRequestOpenedAt = null;

    #[ORM\Column(name: 'merge_request_merged_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $mergeRequestMergedAt = null;

    #[ORM\Column(name: 'merge_request_closed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $mergeRequestClosedAt = null;

    /**
     * When this merge request's state was last known to be current — set by whoever told
     * us about it, the runner or the poller. It is what orders the polling queue (least
     * recently checked first), so the poller stamps it even for a merge request GitLab
     * could not be asked about: an organization whose connection is broken must not
     * monopolise every pass.
     */
    #[ORM\Column(name: 'merge_request_checked_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $mergeRequestCheckedAt = null;

    #[ORM\Column(name: 'pipeline_status', type: Types::STRING, enumType: PipelineStatusEnum::class, options: ['default' => 'not_watched'])]
    private PipelineStatusEnum $pipelineStatus = PipelineStatusEnum::NOT_WATCHED;

    /** The commit Refleet itself last pushed; a merge request at any other head has moved on without it. */
    #[ORM\Column(name: 'pipeline_head_sha', type: Types::STRING, length: 64, nullable: true)]
    private ?string $pipelineHeadSha = null;

    #[ORM\Column(name: 'pipeline_watch_started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $pipelineWatchStartedAt = null;

    #[ORM\Column(name: 'pipeline_checked_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $pipelineCheckedAt = null;

    #[ORM\Column(name: 'pipeline_fix_attempts', type: Types::INTEGER, options: ['default' => 0])]
    private int $pipelineFixAttempts = 0;

    #[ORM\Column(name: 'pipeline_fix_job_id', type: Types::GUID, nullable: true)]
    private ?string $pipelineFixJobId = null;

    /** Kept across re-runs: a pipeline already sent to an agent must never be sent again. */
    #[ORM\Column(name: 'pipeline_last_fixed_id', type: Types::STRING, length: 64, nullable: true)]
    private ?string $pipelineLastFixedId = null;

    #[ORM\Column(name: 'pipeline_summary', type: Types::TEXT, nullable: true)]
    private ?string $pipelineSummary = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        ShiftTargetId $id,
        ShiftId $shiftId,
        OrganizationId $organizationId,
        ProjectId $projectId,
        ProjectSnapshot $snapshot,
    ) {
        $this->id = $id->asString();
        $this->shiftId = $shiftId->asString();
        $this->organizationId = $organizationId->asString();
        $this->projectId = $projectId->asString();
        $this->projectSnapshotExternalId = $snapshot->externalId();
        $this->projectSnapshotPath = $snapshot->path();
        $this->projectSnapshotName = $snapshot->name();
        $this->projectSnapshotDefaultBranch = $snapshot->defaultBranch();
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function create(
        ShiftTargetId $id,
        ShiftId $shiftId,
        OrganizationId $organizationId,
        ProjectId $projectId,
        ProjectSnapshot $snapshot,
    ): self {
        return new self($id, $shiftId, $organizationId, $projectId, $snapshot);
    }

    public function startChange(string $runnerJobId): void
    {
        $this->guardStatus([ShiftTargetStatusEnum::PENDING_CHANGE], 'start change');

        $this->status = ShiftTargetStatusEnum::CHANGE_IN_PROGRESS;
        $this->runnerJobId = $runnerJobId;
        $this->changeStartedAt = new \DateTimeImmutable();
    }

    /**
     * The runner reports the merge request it opened together with the outcome, so the
     * target lands in MERGE_REQUEST_OPEN already knowing where that MR is. A success
     * without one means the agent found nothing to change — settled, but nothing to
     * review. The pipeline is only watched when the runner reported the commit it pushed:
     * without that there is no telling Refleet's head from someone else's, so a runner too
     * old to report it simply leaves the merge request unwatched.
     *
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag") The shift's own setting, read by the caller.
     */
    public function recordChangeSuccess(string $summary, ?string $branchName, string $runnerName, ?string $mergeRequestUrl = null, ?string $mergeRequestIid = null, ?string $headSha = null, bool $watchPipeline = false): void
    {
        $this->guardStatus([ShiftTargetStatusEnum::CHANGE_IN_PROGRESS], 'record change success');

        $this->changeSummary = $summary;
        $this->changeBranchName = $branchName;
        $this->runnerName = $runnerName;
        $this->changeCompletedAt = new \DateTimeImmutable();

        if (null === $mergeRequestUrl) {
            $this->status = ShiftTargetStatusEnum::NO_CHANGES;

            return;
        }

        $this->status = ShiftTargetStatusEnum::MERGE_REQUEST_OPEN;
        $this->mergeRequestUrl = $mergeRequestUrl;
        $this->mergeRequestExternalIid = $mergeRequestIid;
        $this->mergeRequestStatus = MergeRequestStatusEnum::OPEN;
        $this->mergeRequestOpenedAt ??= new \DateTimeImmutable();
        $this->mergeRequestCheckedAt = new \DateTimeImmutable();

        if ($watchPipeline && null !== $headSha && null !== $mergeRequestIid) {
            $this->watchPipelineOf($headSha);
        }
    }

    public function recordChangeFailure(string $error, string $runnerName): void
    {
        $this->guardStatus([ShiftTargetStatusEnum::CHANGE_IN_PROGRESS], 'record change failure');

        $this->status = ShiftTargetStatusEnum::CHANGE_FAILED;
        $this->changeSummary = $error;
        $this->runnerName = $runnerName;
        $this->changeCompletedAt = new \DateTimeImmutable();
    }

    /**
     * Sends a settled target through the agent again. The merge request fields are left
     * untouched on purpose: the runner force-pushes the same per-target branch and
     * refreshes the MR already open for it, so the link stays valid throughout — a new
     * MR only appears (and replaces these) when the previous one had been closed.
     */
    public function rerunChange(string $runnerJobId): void
    {
        $this->guardStatus(ShiftTargetStatusEnum::rerunnableStatuses(), 'rerun change');

        $this->status = ShiftTargetStatusEnum::CHANGE_IN_PROGRESS;
        $this->runnerJobId = $runnerJobId;
        $this->changeSummary = null;
        $this->changeStartedAt = new \DateTimeImmutable();
        $this->changeCompletedAt = null;
        $this->stopWatchingPipeline();
        $this->pipelineFixAttempts = 0;
        $this->pipelineSummary = null;
    }

    /**
     * Idempotent refinement: safe to call again on a target that is already
     * MERGE_REQUEST_OPEN, e.g. to fill in the URL/IID once GitLab reports them.
     */
    public function recordMergeRequestOpened(?string $url, ?string $externalIid): void
    {
        $this->guardStatus([ShiftTargetStatusEnum::MERGE_REQUEST_OPEN], 'record merge request opened');

        $this->mergeRequestStatus = MergeRequestStatusEnum::OPEN;

        if (null !== $url) {
            $this->mergeRequestUrl = $url;
        }

        if (null !== $externalIid) {
            $this->mergeRequestExternalIid = $externalIid;
        }

        $this->mergeRequestOpenedAt ??= new \DateTimeImmutable();
        $this->mergeRequestCheckedAt = new \DateTimeImmutable();
    }

    /**
     * The poller asked GitLab about this merge request and is done with it for now,
     * whatever the answer was — including no answer at all.
     */
    public function recordMergeRequestChecked(): void
    {
        $this->mergeRequestCheckedAt = new \DateTimeImmutable();
    }

    public function recordMergeRequestMerged(): void
    {
        $this->guardStatus([ShiftTargetStatusEnum::MERGE_REQUEST_OPEN], 'record merge request merged');

        $this->status = ShiftTargetStatusEnum::COMPLETED;
        $this->mergeRequestStatus = MergeRequestStatusEnum::MERGED;
        $this->mergeRequestMergedAt = new \DateTimeImmutable();
        $this->stopWatchingPipeline();
    }

    public function recordMergeRequestClosed(): void
    {
        $this->guardStatus([ShiftTargetStatusEnum::MERGE_REQUEST_OPEN], 'record merge request closed');

        $this->status = ShiftTargetStatusEnum::MERGE_REQUEST_CLOSED;
        $this->mergeRequestStatus = MergeRequestStatusEnum::CLOSED;
        $this->mergeRequestClosedAt = new \DateTimeImmutable();
        $this->stopWatchingPipeline();
    }

    public function recordPipelineChecked(): void
    {
        $this->pipelineCheckedAt = new \DateTimeImmutable();
    }

    /**
     * Takes in what GitLab reports and returns true when an agent should look at a failed
     * pipeline. Anything that does not clearly belong to the commit Refleet pushed is not a
     * reason to act: a head it did not push means a person has got involved, and a pipeline
     * from an earlier commit says nothing about this one.
     */
    public function observePipeline(MergeRequestPipeline $observed): bool
    {
        if (PipelineStatusEnum::WAITING !== $this->pipelineStatus) {
            return false;
        }

        if ($observed->headSha !== $this->pipelineHeadSha) {
            $this->pipelineStatus = PipelineStatusEnum::STOOD_DOWN;

            return false;
        }

        if (!$observed->isOfHead() || $observed->pipelineId === $this->pipelineLastFixedId) {
            if ($this->pipelineWatchStartedAt < new \DateTimeImmutable(self::NO_PIPELINE_AFTER)) {
                $this->pipelineStatus = PipelineStatusEnum::NO_PIPELINE;
            }

            return false;
        }

        return match ($observed->pipelineStatus) {
            'success' => $this->settlePipeline(PipelineStatusEnum::PASSED),
            'failed' => $this->pipelineFixAttempts < self::MAX_PIPELINE_FIXES || $this->settlePipeline(PipelineStatusEnum::GAVE_UP),
            'canceled', 'canceling', 'skipped', 'manual' => $this->settlePipeline(PipelineStatusEnum::INCONCLUSIVE),
            default => false,
        };
    }

    public function startPipelineFix(string $jobId, string $pipelineId): void
    {
        $this->guardStatus([ShiftTargetStatusEnum::MERGE_REQUEST_OPEN], 'start pipeline fix');

        $this->pipelineStatus = PipelineStatusEnum::FIXING;
        $this->pipelineFixJobId = $jobId;
        $this->pipelineLastFixedId = $pipelineId;
        ++$this->pipelineFixAttempts;
    }

    /**
     * Ignored unless the job is still this target's current fix: a re-run, a merge or a
     * close during the fix has already decided what happens next, and a late report must
     * not undo it.
     */
    public function recordPipelineFixResult(string $jobId, PipelineFixOutcomeEnum $outcome, string $summary, ?string $headSha = null): void
    {
        if (
            ShiftTargetStatusEnum::MERGE_REQUEST_OPEN !== $this->status
            || PipelineStatusEnum::FIXING !== $this->pipelineStatus
            || $jobId !== $this->pipelineFixJobId
        ) {
            return;
        }

        $this->pipelineFixJobId = null;
        $this->pipelineSummary = $summary;

        if (PipelineFixOutcomeEnum::FIXED === $outcome && null !== $headSha) {
            $this->watchPipelineOf($headSha);

            return;
        }

        if (PipelineFixOutcomeEnum::JOB_FAILED === $outcome) {
            $this->sendPipelineBackToThePoll();

            return;
        }

        $this->pipelineStatus = match ($outcome) {
            PipelineFixOutcomeEnum::UNRELATED => PipelineStatusEnum::UNRELATED,
            PipelineFixOutcomeEnum::SUPERSEDED => PipelineStatusEnum::STOOD_DOWN,
            default => PipelineStatusEnum::GAVE_UP,
        };
    }

    /**
     * A person asking for another go, once the attempts ran out or the agent found nothing to
     * do. The poll decides from there, so a pipeline that has since gone green just settles.
     */
    public function retryPipelineFix(): void
    {
        if (ShiftTargetStatusEnum::MERGE_REQUEST_OPEN !== $this->status || PipelineStatusEnum::GAVE_UP !== $this->pipelineStatus) {
            throw new InvalidShiftTargetStateTransitionException($this->status, 'retry pipeline fix');
        }

        $this->pipelineFixAttempts = 0;
        $this->pipelineSummary = null;
        $this->sendPipelineBackToThePoll();
    }

    public function cancel(): void
    {
        if ($this->isTerminal()) {
            throw new InvalidShiftTargetStateTransitionException($this->status, 'cancel');
        }

        $this->status = ShiftTargetStatusEnum::CANCELLED;
        $this->stopWatchingPipeline();
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function id(): ShiftTargetId
    {
        return ShiftTargetId::fromString($this->id);
    }

    public function shiftId(): ShiftId
    {
        return ShiftId::fromString($this->shiftId);
    }

    public function organizationId(): OrganizationId
    {
        return OrganizationId::fromString($this->organizationId);
    }

    public function projectId(): ProjectId
    {
        return ProjectId::fromString($this->projectId);
    }

    public function projectSnapshot(): ProjectSnapshot
    {
        return new ProjectSnapshot(
            $this->projectSnapshotExternalId,
            $this->projectSnapshotPath,
            $this->projectSnapshotName,
            $this->projectSnapshotDefaultBranch,
        );
    }

    public function status(): ShiftTargetStatusEnum
    {
        return $this->status;
    }

    public function changeSummary(): ?string
    {
        return $this->changeSummary;
    }

    public function changeBranchName(): ?string
    {
        return $this->changeBranchName;
    }

    public function runnerJobId(): ?string
    {
        return $this->runnerJobId;
    }

    public function runnerName(): ?string
    {
        return $this->runnerName;
    }

    public function changeStartedAt(): ?\DateTimeImmutable
    {
        return $this->changeStartedAt;
    }

    public function changeCompletedAt(): ?\DateTimeImmutable
    {
        return $this->changeCompletedAt;
    }

    public function mergeRequestUrl(): ?string
    {
        return $this->mergeRequestUrl;
    }

    public function mergeRequestExternalIid(): ?string
    {
        return $this->mergeRequestExternalIid;
    }

    public function mergeRequestStatus(): MergeRequestStatusEnum
    {
        return $this->mergeRequestStatus;
    }

    public function mergeRequestOpenedAt(): ?\DateTimeImmutable
    {
        return $this->mergeRequestOpenedAt;
    }

    public function mergeRequestMergedAt(): ?\DateTimeImmutable
    {
        return $this->mergeRequestMergedAt;
    }

    public function mergeRequestClosedAt(): ?\DateTimeImmutable
    {
        return $this->mergeRequestClosedAt;
    }

    public function mergeRequestCheckedAt(): ?\DateTimeImmutable
    {
        return $this->mergeRequestCheckedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function pipelineStatus(): PipelineStatusEnum
    {
        return $this->pipelineStatus;
    }

    public function pipelineHeadSha(): ?string
    {
        return $this->pipelineHeadSha;
    }

    public function pipelineFixAttempts(): int
    {
        return $this->pipelineFixAttempts;
    }

    public function pipelineFixJobId(): ?string
    {
        return $this->pipelineFixJobId;
    }

    public function pipelineCheckedAt(): ?\DateTimeImmutable
    {
        return $this->pipelineCheckedAt;
    }

    public function pipelineSummary(): ?string
    {
        return $this->pipelineSummary;
    }

    private function watchPipelineOf(string $headSha): void
    {
        $this->pipelineStatus = PipelineStatusEnum::WAITING;
        $this->pipelineHeadSha = $headSha;
        $this->pipelineWatchStartedAt = new \DateTimeImmutable();
        $this->pipelineFixJobId = null;
    }

    /**
     * Forgetting the pipeline already sent to an agent is what lets the same one be sent
     * again; the attempt limit is what stops that going on for ever.
     */
    private function sendPipelineBackToThePoll(): void
    {
        $this->pipelineStatus = PipelineStatusEnum::WAITING;
        $this->pipelineWatchStartedAt = new \DateTimeImmutable();
        $this->pipelineCheckedAt = null;
        $this->pipelineFixJobId = null;
        $this->pipelineLastFixedId = null;
    }

    /**
     * A settled outcome such as PASSED stays readable on a merged merge request; only a
     * watch still in progress is called off.
     */
    private function stopWatchingPipeline(): void
    {
        $this->pipelineFixJobId = null;

        if (\in_array($this->pipelineStatus, [PipelineStatusEnum::WAITING, PipelineStatusEnum::FIXING], true)) {
            $this->pipelineStatus = PipelineStatusEnum::NOT_WATCHED;
        }
    }

    private function settlePipeline(PipelineStatusEnum $status): false
    {
        $this->pipelineStatus = $status;

        return false;
    }

    /**
     * @param ShiftTargetStatusEnum[] $allowed
     */
    private function guardStatus(array $allowed, string $attemptedAction): void
    {
        if (!\in_array($this->status, $allowed, true)) {
            throw new InvalidShiftTargetStateTransitionException($this->status, $attemptedAction);
        }
    }
}
