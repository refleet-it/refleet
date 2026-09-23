<?php

declare(strict_types=1);

namespace App\File\File\Infrastructure\Persistence;

use App\File\File\Domain\Model\File;
use App\File\File\Domain\Repository\FileRepositoryInterface;
use App\File\File\Domain\ValueObject\FileId;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\Status;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final readonly class FileRepository implements FileRepositoryInterface
{
    /** @var EntityRepository<File> */
    private EntityRepository $repository;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        $this->repository = $this->entityManager->getRepository(File::class);
    }

    #[\Override]
    public function save(File $file): void
    {
        $this->entityManager->persist($file);
        $this->entityManager->flush();
    }

    #[\Override]
    public function findByUploaderId(UserId $uploaderId): array
    {
        /** @var File[] $files */
        $files = $this->repository->findBy(['uploaderId' => $uploaderId->asString()]);

        return $files;
    }

    #[\Override]
    public function findActiveByUploaderId(UserId $uploaderId): array
    {
        /** @var File[] $files */
        $files = $this->repository->findBy([
            'uploaderId' => $uploaderId->asString(),
            'status' => Status::ACTIVE,
        ]);

        return $files;
    }

    #[\Override]
    public function exists(FileId $id): bool
    {
        return $this->repository->count(['id' => $id->asString()]) > 0;
    }

    #[\Override]
    public function delete(FileId $id): void
    {
        $file = $this->findById($id);
        if (null !== $file) {
            $this->entityManager->remove($file);
            $this->entityManager->flush();
        }
    }

    #[\Override]
    public function findById(FileId $id): ?File
    {
        /** @var File|null $file */
        $file = $this->repository->find($id->asString());

        return $file;
    }

    #[\Override]
    public function findByPath(string $path): ?File
    {
        /** @var File|null $file */
        $file = $this->repository->findOneBy(['path' => $path])
            ?? $this->repository->findOneBy(['thumbnailPath' => $path]);

        return $file;
    }

    #[\Override]
    public function countByUploaderId(UserId $uploaderId): int
    {
        return $this->repository->count(['uploaderId' => $uploaderId->asString()]);
    }

    #[\Override]
    public function findByTenantId(TenantId $tenantId): array
    {
        /** @var File[] $files */
        $files = $this->repository->findBy(['tenantId' => $tenantId->asString()]);

        return $files;
    }

    #[\Override]
    public function findActiveByTenantId(TenantId $tenantId): array
    {
        /** @var File[] $files */
        $files = $this->repository->findBy([
            'tenantId' => $tenantId->asString(),
            'status' => Status::ACTIVE,
        ]);

        return $files;
    }

    #[\Override]
    public function countByTenantId(TenantId $tenantId): int
    {
        return $this->repository->count(['tenantId' => $tenantId->asString()]);
    }
}
