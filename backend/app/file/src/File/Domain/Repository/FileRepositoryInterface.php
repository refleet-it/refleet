<?php

declare(strict_types=1);

namespace App\File\File\Domain\Repository;

use App\File\File\Domain\Model\File;
use App\File\File\Domain\ValueObject\FileId;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\TenantId;

interface FileRepositoryInterface
{
    public function save(File $file): void;

    public function findById(FileId $id): ?File;

    public function findByPath(string $path): ?File;

    /**
     * @return File[]
     */
    public function findByUploaderId(UserId $uploaderId): array;

    /**
     * @return File[]
     */
    public function findByTenantId(TenantId $tenantId): array;

    /**
     * @return File[]
     */
    public function findActiveByUploaderId(UserId $uploaderId): array;

    /**
     * @return File[]
     */
    public function findActiveByTenantId(TenantId $tenantId): array;

    public function countByUploaderId(UserId $uploaderId): int;

    public function countByTenantId(TenantId $tenantId): int;

    public function delete(FileId $id): void;

    public function exists(FileId $id): bool;
}
