<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Domain\Playbook\Model;

use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookAppliesToEnum;
use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookKindEnum;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\AccountId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\OrganizationId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookParameter;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Event\AggregateRoot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A reusable prompt fragment an organization keeps: a task (the change or criteria, with
 * parameters) or a rule (a standing instruction). Playbooks only ever produce text — the
 * Shift or Qualification stores what was composed, never a reference back here, so editing
 * or deleting a playbook cannot change anything already running.
 */
#[ORM\Entity]
#[ORM\Table(name: 'playbooks', schema: 'playbook')]
#[ORM\Index(name: 'idx_playbooks_organization_id', columns: ['organization_id'])]
class Playbook extends AggregateRoot
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(name: 'organization_id', type: Types::GUID)]
    private string $organizationId;

    #[ORM\Column(name: 'created_by', type: Types::GUID)]
    private string $createdBy;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(type: Types::STRING, enumType: PlaybookKindEnum::class)]
    private PlaybookKindEnum $kind;

    #[ORM\Column(name: 'applies_to', type: Types::STRING, enumType: PlaybookAppliesToEnum::class)]
    private PlaybookAppliesToEnum $appliesTo;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(name: 'is_default', type: Types::BOOLEAN)]
    private bool $default;

    /**
     * @var list<array{name: string, label: string, default: string|null, required: bool}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $parameters;

    #[ORM\Column(type: Types::STRING, nullable: true, enumType: CriteriaEngineEnum::class)]
    private ?CriteriaEngineEnum $engine;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $model;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    private function __construct(PlaybookId $id, OrganizationId $organizationId, AccountId $createdBy, PlaybookDefinition $definition)
    {
        $this->id = $id->asString();
        $this->organizationId = $organizationId->asString();
        $this->createdBy = $createdBy->asString();
        $this->createdAt = new \DateTimeImmutable();
        $this->apply($definition);
    }

    /**
     * $definition's own id and builtIn flag are ignored — the aggregate is the identity.
     */
    public static function create(PlaybookId $id, OrganizationId $organizationId, AccountId $createdBy, PlaybookDefinition $definition): self
    {
        return new self($id, $organizationId, $createdBy, $definition);
    }

    public function update(PlaybookDefinition $definition): void
    {
        $this->apply($definition);
    }

    public function id(): PlaybookId
    {
        return PlaybookId::fromString($this->id);
    }

    public function organizationId(): OrganizationId
    {
        return OrganizationId::fromString($this->organizationId);
    }

    public function createdBy(): AccountId
    {
        return AccountId::fromString($this->createdBy);
    }

    public function definition(): PlaybookDefinition
    {
        return new PlaybookDefinition(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            kind: $this->kind,
            appliesTo: $this->appliesTo,
            body: $this->body,
            default: $this->default,
            parameters: \array_map(PlaybookParameter::fromArray(...), $this->parameters),
            engine: $this->engine,
            model: $this->model,
            builtIn: false,
        );
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function apply(PlaybookDefinition $definition): void
    {
        $this->name = $definition->name();
        $this->description = $definition->description();
        $this->kind = $definition->kind();
        $this->appliesTo = $definition->appliesTo();
        $this->body = $definition->body();
        $this->default = $definition->isDefault();
        $this->parameters = \array_map(static fn (PlaybookParameter $parameter): array => $parameter->toArray(), $definition->parameters());
        $this->engine = $definition->engine();
        $this->model = $definition->model();
        $this->updatedAt = new \DateTimeImmutable();
    }
}
