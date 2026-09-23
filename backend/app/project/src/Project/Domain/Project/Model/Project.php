<?php

declare(strict_types=1);

namespace App\Project\Project\Domain\Project\Model;

use App\Project\Project\Domain\Project\Event\ProjectRegistered;
use App\Project\Project\Domain\Project\ValueObject\GitLabProjectId;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Project\Project\Domain\Project\ValueObject\ProjectId;
use App\Shared\Domain\Event\AggregateRoot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'projects', schema: 'project')]
#[ORM\Index(name: 'idx_projects_organization_id', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_projects_active', columns: ['organization_id'], options: ['where' => '(archived_at IS NULL)'])]
#[ORM\UniqueConstraint(name: 'uniq_projects_organization_external_id', columns: ['organization_id', 'external_id'])]
class Project extends AggregateRoot
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(name: 'organization_id', type: Types::GUID)]
    private string $organizationId;

    #[ORM\Column(name: 'external_id', type: Types::STRING, length: 32)]
    private string $externalId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_synced_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column(name: 'archived_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    private function __construct(
        ProjectId $id,
        OrganizationId $organizationId,
        GitLabProjectId $externalId,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $name,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $path,
        #[ORM\Column(name: 'web_url', type: Types::STRING, length: 500, nullable: true)]
        private ?string $webUrl,
        #[ORM\Column(name: 'default_branch', type: Types::STRING, length: 100, nullable: true)]
        private ?string $defaultBranch,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $description,
    ) {
        $this->id = $id->asString();
        $this->organizationId = $organizationId->asString();
        $this->externalId = $externalId->asString();
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function register(
        ProjectId $id,
        OrganizationId $organizationId,
        GitLabProjectId $externalId,
        string $name,
        string $path,
        ?string $webUrl,
        ?string $defaultBranch,
        ?string $description,
    ): self {
        $project = new self($id, $organizationId, $externalId, $name, $path, $webUrl, $defaultBranch, $description);
        $project->lastSyncedAt = new \DateTimeImmutable();
        $project->recordThat(new ProjectRegistered($id, $organizationId, $name));

        return $project;
    }

    public function update(
        string $name,
        string $path,
        ?string $webUrl,
        ?string $defaultBranch,
        ?string $description,
    ): void {
        $this->name = $name;
        $this->path = $path;
        $this->webUrl = $webUrl;
        $this->defaultBranch = $defaultBranch;
        $this->description = $description;
        $this->lastSyncedAt = new \DateTimeImmutable();
        $this->archivedAt = null;
    }

    public function archive(): void
    {
        $this->archivedAt ??= new \DateTimeImmutable();
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function id(): ProjectId
    {
        return ProjectId::fromString($this->id);
    }

    public function organizationId(): OrganizationId
    {
        return OrganizationId::fromString($this->organizationId);
    }

    public function externalId(): GitLabProjectId
    {
        return GitLabProjectId::fromString($this->externalId);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function webUrl(): ?string
    {
        return $this->webUrl;
    }

    public function defaultBranch(): ?string
    {
        return $this->defaultBranch;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }
}
