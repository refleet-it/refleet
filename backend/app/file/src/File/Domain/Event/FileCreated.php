<?php

declare(strict_types=1);

namespace App\File\File\Domain\Event;

use App\File\File\Domain\ValueObject\FileId;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class FileCreated implements DomainEventInterface
{
    public function __construct(
        private FileId $fileId,
        private string $fileName,
        private UserId $uploaderId,
        private ?TenantId $tenantId,
    ) {
    }

    public function fileId(): FileId
    {
        return $this->fileId;
    }

    public function fileName(): string
    {
        return $this->fileName;
    }

    public function uploaderId(): UserId
    {
        return $this->uploaderId;
    }

    public function tenantId(): ?TenantId
    {
        return $this->tenantId;
    }
}
