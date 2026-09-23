<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Domain\Qualification\Model;

use App\Qualification\Qualification\Domain\Qualification\Enum\QualificationStatusEnum;
use App\Qualification\Qualification\Domain\Qualification\Event\QualificationCancelled;
use App\Qualification\Qualification\Domain\Qualification\Event\QualificationCompleted;
use App\Qualification\Qualification\Domain\Qualification\Exception\InvalidQualificationStateTransitionException;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationArchivedException;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use App\Shared\Domain\Event\AggregateRoot;
use App\Shared\Domain\ValueObject\PromptSource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A standalone, reusable artifact: find/evaluate which projects match a criteria, then
 * let any number of Shifts be created from the resulting (and possibly overridden)
 * target list. There is no "review" phase gating anything downstream — once COMPLETED,
 * this is simply durable data.
 */
#[ORM\Entity]
#[ORM\Table(name: 'qualifications', schema: 'qualification')]
#[ORM\Index(name: 'idx_qualifications_organization_id_status', columns: ['organization_id', 'status'])]
#[ORM\Index(name: 'idx_qualifications_archived_at', columns: ['archived_at'])]
class Qualification extends AggregateRoot
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(name: 'organization_id', type: Types::GUID)]
    private string $organizationId;

    #[ORM\Column(name: 'created_by', type: Types::GUID)]
    private string $createdBy;

    #[ORM\Column(type: Types::STRING, enumType: QualificationStatusEnum::class)]
    private QualificationStatusEnum $status = QualificationStatusEnum::DRAFT;

    #[ORM\Column(name: 'criteria_mode', type: Types::STRING, enumType: CriteriaModeEnum::class)]
    private CriteriaModeEnum $criteriaMode;

    #[ORM\Column(name: 'criteria_engine', type: Types::STRING, nullable: true, enumType: CriteriaEngineEnum::class)]
    private ?CriteriaEngineEnum $criteriaEngine = null;

    #[ORM\Column(name: 'criteria_prompt', type: Types::TEXT)]
    private string $criteriaPrompt;

    #[ORM\Column(name: 'criteria_model', type: Types::STRING, nullable: true)]
    private ?string $criteriaModel = null;

    #[ORM\Column(name: 'criteria_rules', type: Types::TEXT, nullable: true)]
    private ?string $criteriaRules = null;

    /**
     * @var list<array{id: string, name: string, kind: string, builtIn: bool}>|null
     */
    #[ORM\Column(name: 'criteria_sources', type: Types::JSON, nullable: true)]
    private ?array $criteriaSources = null;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

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
        QualificationId $id,
        OrganizationId $organizationId,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $title,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $description,
        AccountId $createdBy,
        QualificationCriteria $criteria,
    ) {
        $this->id = $id->asString();
        $this->organizationId = $organizationId->asString();
        $this->createdBy = $createdBy->asString();
        $this->criteriaMode = $criteria->mode();
        $this->criteriaEngine = $criteria->engine();
        $this->criteriaPrompt = $criteria->prompt();
        $this->criteriaModel = $criteria->model();
        $this->criteriaRules = $criteria->rules();
        $this->criteriaSources = [] === $criteria->sources()
            ? null
            : \array_map(static fn (PromptSource $source): array => $source->toArray(), $criteria->sources());
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function draft(
        QualificationId $id,
        OrganizationId $organizationId,
        string $title,
        ?string $description,
        AccountId $createdBy,
        QualificationCriteria $criteria,
    ): self {
        return new self($id, $organizationId, $title, $description, $createdBy, $criteria);
    }

    public function start(): void
    {
        $this->guardStatus([QualificationStatusEnum::DRAFT], 'start');

        $this->status = QualificationStatusEnum::RUNNING;
        $this->startedAt = new \DateTimeImmutable();
    }

    /**
     * Marks the batch as no longer "in flight" — this does NOT mean every target
     * qualified, only that no target is still PENDING/IN_PROGRESS.
     */
    public function complete(): void
    {
        $this->guardStatus([QualificationStatusEnum::RUNNING], 'complete');

        $this->status = QualificationStatusEnum::COMPLETED;
        $this->completedAt = new \DateTimeImmutable();

        $this->recordThat(new QualificationCompleted(
            $this->id(),
            $this->organizationId(),
            $this->createdBy(),
            $this->title,
        ));
    }

    /**
     * Re-opens a completed batch because some of its targets were sent back to the queue
     * (see QualificationTarget::retry()). Completes again once they settle, firing
     * QualificationCompleted a second time — the reviewer wants to hear about the
     * retried results just like the first ones.
     */
    public function resume(): void
    {
        $this->guardStatus([QualificationStatusEnum::COMPLETED], 'resume');

        $this->status = QualificationStatusEnum::RUNNING;
        $this->completedAt = null;
    }

    public function cancel(?string $reason): void
    {
        $this->guardStatus([QualificationStatusEnum::DRAFT, QualificationStatusEnum::RUNNING], 'cancel');

        $this->status = QualificationStatusEnum::CANCELLED;
        $this->cancelReason = $reason;
        $this->cancelledAt = new \DateTimeImmutable();

        $this->recordThat(new QualificationCancelled(
            $this->id(),
            $this->organizationId(),
            $this->createdBy(),
            $this->title,
            $reason,
        ));
    }

    /**
     * Archiving hides a qualification from the live list and from the "create a shift from"
     * picker, and freezes it: no further transitions, overrides or shifts built on it. A
     * running qualification has to be cancelled first, so nothing in flight is orphaned.
     */
    public function archive(\DateTimeImmutable $at): void
    {
        $this->guardStatus([QualificationStatusEnum::DRAFT, QualificationStatusEnum::COMPLETED, QualificationStatusEnum::CANCELLED], 'archive');

        $this->archivedAt = $at;
    }

    public function id(): QualificationId
    {
        return QualificationId::fromString($this->id);
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

    public function status(): QualificationStatusEnum
    {
        return $this->status;
    }

    public function criteria(): QualificationCriteria
    {
        return match ($this->criteriaMode) {
            CriteriaModeEnum::AI => QualificationCriteria::ai(
                $this->criteriaPrompt,
                $this->criteriaModel,
                $this->criteriaEngine,
                $this->criteriaRules,
                \array_map(PromptSource::fromArray(...), $this->criteriaSources ?? []),
            ),
        };
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
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
     * @param QualificationStatusEnum[] $allowed
     */
    private function guardStatus(array $allowed, string $attemptedAction): void
    {
        if ($this->isArchived()) {
            throw new QualificationArchivedException();
        }

        if (!\in_array($this->status, $allowed, true)) {
            throw new InvalidQualificationStateTransitionException($this->status, $attemptedAction);
        }
    }
}
