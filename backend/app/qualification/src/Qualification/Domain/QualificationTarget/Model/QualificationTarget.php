<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Domain\QualificationTarget\Model;

use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationScore;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Exception\InvalidQualificationTargetStateTransitionException;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\ProjectId;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use App\Shared\Domain\Event\AggregateRoot;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A deliberately separate aggregate root from Qualification: with many targets updated
 * concurrently by runner callbacks, keeping them as a collection on a single
 * Qualification aggregate would create lock/optimistic-locking contention. Does not
 * record domain events (bulk creation of dozens of targets would be noise on the bus).
 */
#[ORM\Entity]
#[ORM\Table(name: 'qualification_targets', schema: 'qualification')]
#[ORM\Index(name: 'idx_qualification_targets_qualification_id_status', columns: ['qualification_id', 'status'])]
#[ORM\Index(name: 'idx_qualification_targets_organization_id', columns: ['organization_id'])]
#[ORM\UniqueConstraint(name: 'uniq_qualification_targets_qualification_project', columns: ['qualification_id', 'project_id'])]
class QualificationTarget extends AggregateRoot
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(name: 'qualification_id', type: Types::GUID)]
    private string $qualificationId;

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

    #[ORM\Column(type: Types::STRING, enumType: QualificationTargetStatusEnum::class)]
    private QualificationTargetStatusEnum $status = QualificationTargetStatusEnum::PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $score = null;

    #[ORM\Column(name: 'runner_job_id', type: Types::GUID, nullable: true)]
    private ?string $runnerJobId = null;

    #[ORM\Column(name: 'runner_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $runnerName = null;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $overridden = false;

    #[ORM\Column(name: 'override_note', type: Types::TEXT, nullable: true)]
    private ?string $overrideNote = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        QualificationTargetId $id,
        QualificationId $qualificationId,
        OrganizationId $organizationId,
        ProjectId $projectId,
        ProjectSnapshot $snapshot,
    ) {
        $this->id = $id->asString();
        $this->qualificationId = $qualificationId->asString();
        $this->organizationId = $organizationId->asString();
        $this->projectId = $projectId->asString();
        $this->projectSnapshotExternalId = $snapshot->externalId();
        $this->projectSnapshotPath = $snapshot->path();
        $this->projectSnapshotName = $snapshot->name();
        $this->projectSnapshotDefaultBranch = $snapshot->defaultBranch();
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function create(
        QualificationTargetId $id,
        QualificationId $qualificationId,
        OrganizationId $organizationId,
        ProjectId $projectId,
        ProjectSnapshot $snapshot,
    ): self {
        return new self($id, $qualificationId, $organizationId, $projectId, $snapshot);
    }

    public function start(string $runnerJobId): void
    {
        $this->guardStatus([QualificationTargetStatusEnum::PENDING], 'start');

        $this->status = QualificationTargetStatusEnum::IN_PROGRESS;
        $this->runnerJobId = $runnerJobId;
        $this->startedAt = new \DateTimeImmutable();
    }

    public function recordSuccess(QualificationScore $score, string $summary, string $runnerName): void
    {
        $this->guardStatus([QualificationTargetStatusEnum::IN_PROGRESS], 'record success');

        $this->status = $score->qualifies() ? QualificationTargetStatusEnum::QUALIFIED : QualificationTargetStatusEnum::NOT_QUALIFIED;
        $this->score = $score->asInt();
        $this->summary = $summary;
        $this->runnerName = $runnerName;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function recordFailure(string $error, string $runnerName): void
    {
        $this->guardStatus([QualificationTargetStatusEnum::IN_PROGRESS], 'record failure');

        $this->status = QualificationTargetStatusEnum::FAILED;
        $this->summary = $error;
        $this->runnerName = $runnerName;
        $this->completedAt = new \DateTimeImmutable();
    }

    /**
     * Puts a failed target back in the queue for another runner attempt. Only FAILED is
     * retryable: a settled decision is changed through override(), and a target still in
     * flight has its own job to finish.
     */
    public function retry(): void
    {
        $this->guardStatus([QualificationTargetStatusEnum::FAILED], 'retry');

        $this->status = QualificationTargetStatusEnum::PENDING;
        $this->summary = null;
        $this->score = null;
        $this->runnerJobId = null;
        $this->runnerName = null;
        $this->startedAt = null;
        $this->completedAt = null;
    }

    /**
     * Manual decision that supersedes (or substitutes for) the agent's score. Allowed any time except while a runner job is actually in flight for this
     * target, to avoid racing its own report.
     */
    public function override(bool $qualified, ?string $note): void
    {
        $this->guardStatus([
            QualificationTargetStatusEnum::PENDING,
            QualificationTargetStatusEnum::QUALIFIED,
            QualificationTargetStatusEnum::NOT_QUALIFIED,
            QualificationTargetStatusEnum::FAILED,
        ], 'override');

        $this->status = $qualified ? QualificationTargetStatusEnum::QUALIFIED : QualificationTargetStatusEnum::NOT_QUALIFIED;
        $this->overridden = true;
        $this->overrideNote = $note;
        $this->completedAt ??= new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        if ($this->isTerminal()) {
            throw new InvalidQualificationTargetStateTransitionException($this->status, 'cancel');
        }

        $this->status = QualificationTargetStatusEnum::CANCELLED;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function id(): QualificationTargetId
    {
        return QualificationTargetId::fromString($this->id);
    }

    public function qualificationId(): QualificationId
    {
        return QualificationId::fromString($this->qualificationId);
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

    public function status(): QualificationTargetStatusEnum
    {
        return $this->status;
    }

    public function summary(): ?string
    {
        return $this->summary;
    }

    /** The agent's 1–5 verdict behind the automatic status; null until a run succeeds. */
    public function score(): ?int
    {
        return $this->score;
    }

    public function runnerJobId(): ?string
    {
        return $this->runnerJobId;
    }

    public function runnerName(): ?string
    {
        return $this->runnerName;
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function overridden(): bool
    {
        return $this->overridden;
    }

    public function overrideNote(): ?string
    {
        return $this->overrideNote;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @param QualificationTargetStatusEnum[] $allowed
     */
    private function guardStatus(array $allowed, string $attemptedAction): void
    {
        if (!\in_array($this->status, $allowed, true)) {
            throw new InvalidQualificationTargetStateTransitionException($this->status, $attemptedAction);
        }
    }
}
