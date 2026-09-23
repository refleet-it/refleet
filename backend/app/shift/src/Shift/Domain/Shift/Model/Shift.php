<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\Shift\Model;

use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use App\Shared\Domain\Event\AggregateRoot;
use App\Shared\Domain\ValueObject\PromptSource;
use App\Shift\Shift\Domain\Shift\Enum\ShiftStatusEnum;
use App\Shift\Shift\Domain\Shift\Event\ShiftCancelled;
use App\Shift\Shift\Domain\Shift\Event\ShiftCompleted;
use App\Shift\Shift\Domain\Shift\Exception\ChangeCriteriaNotDefinedException;
use App\Shift\Shift\Domain\Shift\Exception\InvalidShiftStateTransitionException;
use App\Shift\Shift\Domain\Shift\Exception\ShiftArchivedException;
use App\Shift\Shift\Domain\Shift\ValueObject\AccountId;
use App\Shift\Shift\Domain\Shift\ValueObject\ChangeCriteria;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\QualificationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Applies a change to a set of targets. The target set is resolved once, at creation
 * (see CreateShiftHandler's 3 modes), from either a Qualification's results or a fully
 * manual project selection — Shift itself only ever deals with "change" criteria, never
 * qualification criteria.
 */
#[ORM\Entity]
#[ORM\Table(name: 'shifts', schema: 'shift')]
#[ORM\Index(name: 'idx_shifts_organization_id_status', columns: ['organization_id', 'status'])]
#[ORM\Index(name: 'idx_shifts_archived_at', columns: ['archived_at'])]
class Shift extends AggregateRoot
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(name: 'organization_id', type: Types::GUID)]
    private string $organizationId;

    #[ORM\Column(name: 'created_by', type: Types::GUID)]
    private string $createdBy;

    #[ORM\Column(name: 'qualification_id', type: Types::GUID, nullable: true)]
    private ?string $qualificationId = null;

    #[ORM\Column(type: Types::STRING, enumType: ShiftStatusEnum::class)]
    private ShiftStatusEnum $status = ShiftStatusEnum::DRAFT;

    #[ORM\Column(name: 'change_mode', type: Types::STRING, nullable: true, enumType: CriteriaModeEnum::class)]
    private ?CriteriaModeEnum $changeMode = null;

    #[ORM\Column(name: 'change_engine', type: Types::STRING, nullable: true, enumType: CriteriaEngineEnum::class)]
    private ?CriteriaEngineEnum $changeEngine = null;

    #[ORM\Column(name: 'change_prompt', type: Types::TEXT, nullable: true)]
    private ?string $changePrompt = null;

    #[ORM\Column(name: 'change_model', type: Types::STRING, nullable: true)]
    private ?string $changeModel = null;

    #[ORM\Column(name: 'change_rules', type: Types::TEXT, nullable: true)]
    private ?string $changeRules = null;

    /**
     * @var list<array{id: string, name: string, kind: string, builtIn: bool}>|null
     */
    #[ORM\Column(name: 'change_sources', type: Types::JSON, nullable: true)]
    private ?array $changeSources = null;

    #[ORM\Column(name: 'change_started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $changeStartedAt = null;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'cancelled_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(name: 'cancel_reason', type: Types::TEXT, nullable: true)]
    private ?string $cancelReason = null;

    #[ORM\Column(name: 'archived_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        ShiftId $id,
        OrganizationId $organizationId,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $title,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $description,
        AccountId $createdBy,
        ?QualificationId $qualificationId,
    ) {
        $this->id = $id->asString();
        $this->organizationId = $organizationId->asString();
        $this->createdBy = $createdBy->asString();
        $this->qualificationId = $qualificationId?->asString();
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function draft(
        ShiftId $id,
        OrganizationId $organizationId,
        string $title,
        ?string $description,
        AccountId $createdBy,
        ?QualificationId $qualificationId,
    ): self {
        return new self($id, $organizationId, $title, $description, $createdBy, $qualificationId);
    }

    /**
     * May be called repeatedly while still DRAFT to redefine the change criteria
     * before the change is actually started.
     */
    public function defineChange(ChangeCriteria $criteria): void
    {
        $this->guardStatus([ShiftStatusEnum::DRAFT], 'define change');

        $this->changeMode = $criteria->mode();
        $this->changeEngine = $criteria->engine();
        $this->changePrompt = $criteria->prompt();
        $this->changeModel = $criteria->model();
        $this->changeRules = $criteria->rules();
        $this->changeSources = [] === $criteria->sources()
            ? null
            : \array_map(static fn (PromptSource $source): array => $source->toArray(), $criteria->sources());
    }

    public function startChange(): void
    {
        $this->guardStatus([ShiftStatusEnum::DRAFT], 'start change');

        if (null === $this->changeMode) {
            throw new ChangeCriteriaNotDefinedException();
        }

        $this->status = ShiftStatusEnum::APPLYING_CHANGE;
        $this->changeStartedAt = new \DateTimeImmutable();
    }

    /**
     * Admits a single target to the agent without committing the whole shift. In DRAFT
     * this is the trial run — one project to see whether the prompt does what it should,
     * with the criteria still editable afterwards. A COMPLETED shift is re-opened so it
     * completes (and notifies) again once the re-run target settles; one already applying
     * simply absorbs the extra job.
     */
    public function startTargetChange(): void
    {
        $this->guardStatus([ShiftStatusEnum::DRAFT, ShiftStatusEnum::APPLYING_CHANGE, ShiftStatusEnum::COMPLETED], 'start target change');

        if (null === $this->changeMode) {
            throw new ChangeCriteriaNotDefinedException();
        }

        if (ShiftStatusEnum::COMPLETED === $this->status) {
            $this->status = ShiftStatusEnum::APPLYING_CHANGE;
            $this->completedAt = null;
        }
    }

    /**
     * Marks the batch as no longer "in flight" — this does NOT mean every target
     * succeeded, only that no target is still CHANGE_IN_PROGRESS/MERGE_REQUEST_OPEN.
     * Read statusBreakdown (on the ShiftTarget side) for the actual outcome distribution.
     */
    public function complete(): void
    {
        $this->guardStatus([ShiftStatusEnum::APPLYING_CHANGE], 'complete');

        $this->status = ShiftStatusEnum::COMPLETED;
        $this->completedAt = new \DateTimeImmutable();

        $this->recordThat(new ShiftCompleted(
            $this->id(),
            $this->organizationId(),
            $this->createdBy(),
            $this->title,
        ));
    }

    public function cancel(?string $reason): void
    {
        $this->guardStatus([ShiftStatusEnum::DRAFT, ShiftStatusEnum::APPLYING_CHANGE], 'cancel');

        $this->status = ShiftStatusEnum::CANCELLED;
        $this->cancelReason = $reason;
        $this->cancelledAt = new \DateTimeImmutable();

        $this->recordThat(new ShiftCancelled(
            $this->id(),
            $this->organizationId(),
            $this->createdBy(),
            $this->title,
            $reason,
        ));
    }

    /**
     * Archiving hides a shift from the live list and freezes it: no further transitions. A
     * shift still applying its change has to be cancelled first, so nothing in flight is
     * orphaned.
     */
    public function archive(\DateTimeImmutable $at): void
    {
        $this->guardStatus([ShiftStatusEnum::DRAFT, ShiftStatusEnum::COMPLETED, ShiftStatusEnum::CANCELLED], 'archive');

        $this->archivedAt = $at;
    }

    public function id(): ShiftId
    {
        return ShiftId::fromString($this->id);
    }

    public function organizationId(): OrganizationId
    {
        return OrganizationId::fromString($this->organizationId);
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function createdBy(): AccountId
    {
        return AccountId::fromString($this->createdBy);
    }

    public function qualificationId(): ?QualificationId
    {
        return null !== $this->qualificationId ? QualificationId::fromString($this->qualificationId) : null;
    }

    public function status(): ShiftStatusEnum
    {
        return $this->status;
    }

    public function changeCriteria(): ?ChangeCriteria
    {
        if (null === $this->changeMode) {
            return null;
        }

        return match ($this->changeMode) {
            CriteriaModeEnum::AI => ChangeCriteria::ai(
                $this->changePrompt ?? '',
                $this->changeModel,
                $this->changeEngine,
                $this->changeRules,
                \array_map(PromptSource::fromArray(...), $this->changeSources ?? []),
            ),
        };
    }

    public function changeStartedAt(): ?\DateTimeImmutable
    {
        return $this->changeStartedAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function cancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function cancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    /**
     * @param ShiftStatusEnum[] $allowed
     */
    private function guardStatus(array $allowed, string $attemptedAction): void
    {
        if ($this->isArchived()) {
            throw new ShiftArchivedException();
        }

        if (!\in_array($this->status, $allowed, true)) {
            throw new InvalidShiftStateTransitionException($this->status, $attemptedAction);
        }
    }
}
