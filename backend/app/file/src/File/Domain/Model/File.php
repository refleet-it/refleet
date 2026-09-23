<?php

declare(strict_types=1);

namespace App\File\File\Domain\Model;

use App\File\File\Domain\Event\FileCreated;
use App\File\File\Domain\Event\FileDeleted;
use App\File\File\Domain\ValueObject\FileId;
use App\File\File\Domain\ValueObject\FileName;
use App\File\File\Domain\ValueObject\FileSize;
use App\File\File\Domain\ValueObject\MimeType;
use App\Shared\Domain\Event\AggregateRoot;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\HasStatus;
use App\Shared\Domain\ValueObject\Status;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'files', schema: 'file')]
class File extends AggregateRoot
{
    use HasStatus;

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(type: Types::STRING)]
    private string $name;

    #[ORM\Column(type: Types::STRING)]
    private string $originalName;

    #[ORM\Column(type: Types::STRING)]
    private string $mimeType;

    #[ORM\Column(type: Types::INTEGER)]
    private int $size;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $thumbnailPath = null;

    #[ORM\Column(type: Types::GUID)]
    private string $uploaderId;

    #[ORM\Column(type: Types::GUID, nullable: true)]
    private ?string $tenantId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::STRING, enumType: Status::class)]
    private Status $status;

    private function __construct(
        FileId $id,
        FileName $name,
        FileName $originalName,
        MimeType $mimeType,
        FileSize $size,
        #[ORM\Column(type: Types::STRING)]
        private string $path,
        #[ORM\Column(type: Types::STRING, nullable: true)]
        private ?string $description,
        UserId $uploaderId,
        ?TenantId $tenantId,
    ) {
        $this->id = $id->asString();
        $this->name = $name->asString();
        $this->originalName = $originalName->asString();
        $this->mimeType = $mimeType->asString();
        $this->size = $size->asInt();
        $this->uploaderId = $uploaderId->asString();
        $this->tenantId = $tenantId?->asString();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->setStatus(Status::ACTIVE);
    }

    public static function create(
        FileId $id,
        FileName $name,
        FileName $originalName,
        MimeType $mimeType,
        FileSize $size,
        string $path,
        ?string $description,
        UserId $uploaderId,
        ?TenantId $tenantId,
    ): self {
        $file = new self(
            $id,
            $name,
            $originalName,
            $mimeType,
            $size,
            $path,
            $description,
            $uploaderId,
            $tenantId
        );

        $file->recordThat(new FileCreated(
            $id,
            $name->asString(),
            $uploaderId,
            $tenantId
        ));

        return $file;
    }

    public function updateDescription(?string $description): void
    {
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function delete(): void
    {
        if ($this->isDeleted()) {
            return;
        }

        $this->setStatus(Status::DELETED);
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordThat(new FileDeleted(
            fileId: FileId::fromString($this->id),
            uploaderId: UserId::fromString($this->uploaderId)
        ));
    }

    public function restore(): void
    {
        if ($this->isActive()) {
            return;
        }

        $this->setStatus(Status::ACTIVE);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function id(): FileId
    {
        return FileId::fromString($this->id);
    }

    public function name(): FileName
    {
        return FileName::fromString($this->name);
    }

    public function originalName(): FileName
    {
        return FileName::fromString($this->originalName);
    }

    public function mimeType(): MimeType
    {
        return MimeType::fromString($this->mimeType);
    }

    public function size(): FileSize
    {
        return FileSize::fromBytes($this->size);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function uploaderId(): UserId
    {
        return UserId::fromString($this->uploaderId);
    }

    public function tenantId(): ?TenantId
    {
        return null !== $this->tenantId ? TenantId::fromString($this->tenantId) : null;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isOwnedBy(UserId $uploaderId): bool
    {
        return $this->uploaderId === $uploaderId->asString();
    }

    public function belongsToTenant(?TenantId $tenantId): bool
    {
        return $this->tenantId === $tenantId?->asString();
    }

    public function thumbnailPath(): ?string
    {
        return $this->thumbnailPath;
    }

    public function setThumbnailPath(?string $thumbnailPath): void
    {
        $this->thumbnailPath = $thumbnailPath;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updatePath(string $path): void
    {
        $this->path = $path;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
