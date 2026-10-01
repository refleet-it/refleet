<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\ShiftTarget\Model;

use App\Shared\Domain\Event\AggregateRoot;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\MergeRequestStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Exception\InvalidShiftTargetStateTransitionException;
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
 * @SuppressWarnings("PHPMD.TooManyFields") Tracks two independent sub-lifecycles
 * (change, merge request) as flattened columns - no Doctrine Embeddables in this
 * codebase - plus one state transition method per lifecycle step.
 * @SuppressWarnings("PHPMD.TooManyMethods") The same cause seen from the other side:
 * every flattened column needs an accessor, and PHPMD counts those as real methods
 * because they carry no "get" or "set" prefix.
 */
#[ORM\Entity]
#[ORM\Table(name: 'shift_targets', schema: 'shift')]
#[ORM\Index(name: 'idx_shift_targets_shift_id_status', columns: ['shift_id', 'status'])]
#[ORM\Index(name: 'idx_shift_targets_organization_id', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_shift_targets_status_mr_checked_at', columns: ['status', 'merge_request_checked_at'])]
#[ORM\UniqueConstraint(name: 'uniq_shift_targets_shift_project', columns: ['shift_id', 'project_id'])]
class ShiftTarget extends AggregateRoot
{
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
     * review.
     */
    public function recordChangeSuccess(string $summary, ?string $branchName, string $runnerName, ?string $mergeRequestUrl = null, ?string $mergeRequestIid = null): void
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
    }

    public function recordMergeRequestClosed(): void
    {
        $this->guardStatus([ShiftTargetStatusEnum::MERGE_REQUEST_OPEN], 'record merge request closed');

        $this->status = ShiftTargetStatusEnum::MERGE_REQUEST_CLOSED;
        $this->mergeRequestStatus = MergeRequestStatusEnum::CLOSED;
        $this->mergeRequestClosedAt = new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        if ($this->isTerminal()) {
            throw new InvalidShiftTargetStateTransitionException($this->status, 'cancel');
        }

        $this->status = ShiftTargetStatusEnum::CANCELLED;
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
