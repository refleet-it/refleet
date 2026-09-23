<?php

declare(strict_types=1);

namespace App\Runner\Runner\Domain\RunnerJob\Model;

use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobKindEnum;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobStatusEnum;
use App\Runner\Runner\Domain\RunnerJob\Exception\InvalidRunnerJobStateTransitionException;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerTargetId;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use App\Shared\Domain\Event\AggregateRoot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A deliberately separate aggregate root, handling both the qualification and change
 * phases (distinguished by `kind`) — they behave identically from the runner's point
 * of view, so one model is enough. `ownerId`/`ownerTargetId` are opaque references into
 * whichever context enqueued the job (Qualification for kind=QUALIFICATION, Shift for
 * kind=CHANGE) — Runner never dereferences them, only round-trips them back on report.
 * Does not record domain events.
 */
#[ORM\Entity]
#[ORM\Table(name: 'runner_jobs', schema: 'runner')]
#[ORM\Index(name: 'idx_runner_jobs_owner_target_id', columns: ['owner_target_id'])]
#[ORM\Index(name: 'idx_runner_jobs_owner_id', columns: ['owner_id'])]
#[ORM\Index(name: 'idx_runner_jobs_claim_lookup', columns: ['organization_id', 'status', 'kind', 'created_at'])]
#[ORM\Index(name: 'idx_runner_jobs_lease_expires_at', columns: ['lease_expires_at'], options: ['where' => "((status)::text = 'claimed'::text)"])]
class RunnerJob extends AggregateRoot
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(name: 'owner_id', type: Types::GUID)]
    private string $ownerId;

    #[ORM\Column(name: 'owner_target_id', type: Types::GUID)]
    private string $ownerTargetId;

    #[ORM\Column(name: 'organization_id', type: Types::GUID)]
    private string $organizationId;

    #[ORM\Column(type: Types::STRING, enumType: RunnerJobStatusEnum::class)]
    private RunnerJobStatusEnum $status = RunnerJobStatusEnum::PENDING;

    #[ORM\Column(name: 'claimed_by', type: Types::STRING, length: 255, nullable: true)]
    private ?string $claimedBy = null;

    #[ORM\Column(name: 'claimed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $claimedAt = null;

    #[ORM\Column(name: 'lease_expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $leaseExpiresAt = null;

    #[ORM\Column(name: 'attempt_count', type: Types::INTEGER, options: ['default' => 0])]
    private int $attemptCount = 0;

    #[ORM\Column(name: 'result_summary', type: Types::TEXT, nullable: true)]
    private ?string $resultSummary = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'result_details', type: Types::JSON, nullable: true)]
    private ?array $resultDetails = null;

    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        RunnerJobId $id,
        RunnerJobOwnerId $ownerId,
        RunnerJobOwnerTargetId $ownerTargetId,
        OrganizationId $organizationId,
        #[ORM\Column(type: Types::STRING, enumType: RunnerJobKindEnum::class)]
        private RunnerJobKindEnum $kind,
        #[ORM\Column(name: 'owner_label', type: Types::STRING, length: 255)]
        private string $ownerLabel,
        #[ORM\Column(type: Types::STRING, enumType: CriteriaModeEnum::class)]
        private CriteriaModeEnum $mode,
        #[ORM\Column(type: Types::JSON)]
        private array $payload,
        #[ORM\Column(type: Types::STRING, nullable: true, enumType: CriteriaEngineEnum::class)]
        private ?CriteriaEngineEnum $engine = null,
    ) {
        $this->id = $id->asString();
        $this->ownerId = $ownerId->asString();
        $this->ownerTargetId = $ownerTargetId->asString();
        $this->organizationId = $organizationId->asString();
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function enqueue(
        RunnerJobId $id,
        RunnerJobOwnerId $ownerId,
        RunnerJobOwnerTargetId $ownerTargetId,
        OrganizationId $organizationId,
        RunnerJobKindEnum $kind,
        string $ownerLabel,
        CriteriaModeEnum $mode,
        array $payload,
        ?CriteriaEngineEnum $engine = null,
    ): self {
        return new self($id, $ownerId, $ownerTargetId, $organizationId, $kind, $ownerLabel, $mode, $payload, $engine);
    }

    /**
     * Domain-level claim, kept for direct aggregate use/testing. The production claim
     * path (DoctrineRunnerJobRepository::claimNext) performs the atomic pick under
     * concurrency via raw SQL and then refreshes the entity from the row it wrote, so
     * this method's guard mirrors — but does not replace — that SQL's WHERE clause.
     */
    public function claim(string $runnerId, int $leaseSeconds): void
    {
        $now = new \DateTimeImmutable();
        $leaseExpired = RunnerJobStatusEnum::CLAIMED === $this->status
            && null !== $this->leaseExpiresAt
            && $this->leaseExpiresAt < $now;

        if (RunnerJobStatusEnum::PENDING !== $this->status && !$leaseExpired) {
            throw new InvalidRunnerJobStateTransitionException($this->status, 'claim');
        }

        $this->status = RunnerJobStatusEnum::CLAIMED;
        $this->claimedBy = $runnerId;
        $this->claimedAt = $now;
        $this->leaseExpiresAt = $now->modify(\sprintf('+%d seconds', $leaseSeconds));
        ++$this->attemptCount;
    }

    /**
     * @param array<string, mixed> $details
     */
    public function reportSuccess(string $summary, array $details = []): void
    {
        $this->guardStatus([RunnerJobStatusEnum::CLAIMED], 'report success');

        $this->status = RunnerJobStatusEnum::SUCCEEDED;
        $this->resultSummary = $summary;
        $this->resultDetails = $details;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function reportFailure(string $errorMessage): void
    {
        $this->guardStatus([RunnerJobStatusEnum::CLAIMED], 'report failure');

        $this->status = RunnerJobStatusEnum::FAILED;
        $this->errorMessage = $errorMessage;
        $this->completedAt = new \DateTimeImmutable();
    }

    /**
     * Cascaded from CancelOwnerJobsCommand when the owning Qualification/Shift is
     * cancelled — only meaningful while there is still work left to stop.
     */
    public function cancel(): void
    {
        $this->guardStatus([RunnerJobStatusEnum::PENDING, RunnerJobStatusEnum::CLAIMED], 'cancel');

        $this->status = RunnerJobStatusEnum::CANCELLED;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function id(): RunnerJobId
    {
        return RunnerJobId::fromString($this->id);
    }

    public function ownerId(): RunnerJobOwnerId
    {
        return RunnerJobOwnerId::fromString($this->ownerId);
    }

    public function ownerTargetId(): RunnerJobOwnerTargetId
    {
        return RunnerJobOwnerTargetId::fromString($this->ownerTargetId);
    }

    public function organizationId(): OrganizationId
    {
        return OrganizationId::fromString($this->organizationId);
    }

    public function kind(): RunnerJobKindEnum
    {
        return $this->kind;
    }

    public function ownerLabel(): string
    {
        return $this->ownerLabel;
    }

    public function mode(): CriteriaModeEnum
    {
        return $this->mode;
    }

    public function engine(): ?CriteriaEngineEnum
    {
        return $this->engine;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function status(): RunnerJobStatusEnum
    {
        return $this->status;
    }

    public function claimedBy(): ?string
    {
        return $this->claimedBy;
    }

    public function claimedAt(): ?\DateTimeImmutable
    {
        return $this->claimedAt;
    }

    public function leaseExpiresAt(): ?\DateTimeImmutable
    {
        return $this->leaseExpiresAt;
    }

    public function attemptCount(): int
    {
        return $this->attemptCount;
    }

    public function resultSummary(): ?string
    {
        return $this->resultSummary;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resultDetails(): ?array
    {
        return $this->resultDetails;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    /**
     * @param RunnerJobStatusEnum[] $allowed
     */
    private function guardStatus(array $allowed, string $attemptedAction): void
    {
        if (!\in_array($this->status, $allowed, true)) {
            throw new InvalidRunnerJobStateTransitionException($this->status, $attemptedAction);
        }
    }
}
