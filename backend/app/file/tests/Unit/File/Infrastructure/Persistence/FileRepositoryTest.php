<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Infrastructure\Persistence;

use App\File\File\Domain\Model\File;
use App\File\File\Domain\ValueObject\FileId;
use App\File\File\Domain\ValueObject\FileName;
use App\File\File\Domain\ValueObject\FileSize;
use App\File\File\Domain\ValueObject\MimeType;
use App\File\File\Infrastructure\Persistence\FileRepository;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\Status;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileRepository::class)]
#[UsesClass(File::class)]
#[UsesClass(FileId::class)]
#[UsesClass(FileName::class)]
#[UsesClass(FileSize::class)]
#[UsesClass(MimeType::class)]
#[UsesClass(UserId::class)]
#[UsesClass(TenantId::class)]
#[UsesClass(Status::class)]
final class FileRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;

    /**
     * @var EntityRepository<File>&MockObject
     */
    private EntityRepository&MockObject $doctrineRepository;

    private FileRepository $repository;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function save_persists_and_flushes_entity(): void
    {
        $file = $this->createFile();

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->identicalTo($file));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->repository->save($file);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function find_by_id_returns_file_when_found(): void
    {
        $id = FileId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $file = $this->createFile($id);

        $this->doctrineRepository
            ->expects($this->once())
            ->method('find')
            ->with($this->identicalTo($id->asString()))
            ->willReturn($file);

        $result = $this->repository->findById($id);

        Assert::assertSame($file, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function find_by_id_returns_null_when_not_found(): void
    {
        $id = FileId::fromString('ffffffff-eeee-dddd-cccc-bbbbbbbbbbbb');

        $this->doctrineRepository
            ->expects($this->once())
            ->method('find')
            ->with($this->identicalTo($id->asString()))
            ->willReturn(null);

        $result = $this->repository->findById($id);

        Assert::assertNull($result);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function find_by_uploader_id_returns_all_matches(): void
    {
        $uploader = UserId::fromString('11111111-2222-3333-4444-555555555555');
        $expected = [$this->createFile(uploader: $uploader)];

        $this->doctrineRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['uploaderId' => $uploader->asString()])
            ->willReturn($expected);

        $result = $this->repository->findByUploaderId($uploader);

        Assert::assertSame($expected, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function find_active_by_uploader_id_filters_by_status(): void
    {
        $uploader = UserId::fromString('aaaa1111-bbbb-2222-cccc-333333333333');
        $activeFiles = [$this->createFile(uploader: $uploader)];

        $this->doctrineRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([
                'uploaderId' => $uploader->asString(),
                'status' => Status::ACTIVE,
            ])
            ->willReturn($activeFiles);

        $result = $this->repository->findActiveByUploaderId($uploader);

        Assert::assertSame($activeFiles, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function exists_returns_true_when_count_positive(): void
    {
        $id = FileId::fromString('99999999-8888-7777-6666-555555555555');

        $this->doctrineRepository
            ->expects($this->once())
            ->method('count')
            ->with(['id' => $id->asString()])
            ->willReturn(3);

        $result = $this->repository->exists($id);

        Assert::assertTrue($result);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function exists_returns_false_when_count_zero(): void
    {
        $id = FileId::fromString('12345678-90ab-cdef-1234-567890abcdef');

        $this->doctrineRepository
            ->expects($this->once())
            ->method('count')
            ->with(['id' => $id->asString()])
            ->willReturn(0);

        $result = $this->repository->exists($id);

        Assert::assertFalse($result);
    }

    #[Test]
    public function delete_removes_and_flushes_when_entity_exists(): void
    {
        $id = FileId::fromString('deadbeef-dead-beef-dead-beefdeadbeef');
        $file = $this->createFile($id);

        $this->doctrineRepository
            ->expects($this->once())
            ->method('find')
            ->with($this->identicalTo($id->asString()))
            ->willReturn($file);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($this->identicalTo($file));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->repository->delete($id);
    }

    #[Test]
    public function delete_is_no_op_when_entity_missing(): void
    {
        $id = FileId::fromString('beadfeed-bead-feed-bead-feedbeadfeed');

        $this->doctrineRepository
            ->expects($this->once())
            ->method('find')
            ->with($this->identicalTo($id->asString()))
            ->willReturn(null);

        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->never())->method('flush');

        $this->repository->delete($id);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function count_by_uploader_id_delegates_to_doctrine(): void
    {
        $uploader = UserId::fromString('cafebabe-cafe-babe-cafe-babecafebabe');

        $this->doctrineRepository
            ->expects($this->once())
            ->method('count')
            ->with(['uploaderId' => $uploader->asString()])
            ->willReturn(5);

        $result = $this->repository->countByUploaderId($uploader);

        Assert::assertSame(5, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function find_by_tenant_id_returns_all_matches(): void
    {
        $tenant = TenantId::fromString('eeee1111-2222-3333-4444-555555555555');
        $expected = [$this->createFile(tenant: $tenant)];

        $this->doctrineRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['tenantId' => $tenant->asString()])
            ->willReturn($expected);

        $result = $this->repository->findByTenantId($tenant);

        Assert::assertSame($expected, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function find_active_by_tenant_id_filters_by_status(): void
    {
        $tenant = TenantId::fromString('66666666-7777-8888-9999-aaaaaaaaaaaa');
        $expected = [$this->createFile(tenant: $tenant)];

        $this->doctrineRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([
                'tenantId' => $tenant->asString(),
                'status' => Status::ACTIVE,
            ])
            ->willReturn($expected);

        $result = $this->repository->findActiveByTenantId($tenant);

        Assert::assertSame($expected, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function count_by_tenant_id_delegates_to_doctrine(): void
    {
        $tenant = TenantId::fromString('abcdef12-3456-7890-abcd-ef1234567890');

        $this->doctrineRepository
            ->expects($this->once())
            ->method('count')
            ->with(['tenantId' => $tenant->asString()])
            ->willReturn(2);

        $result = $this->repository->countByTenantId($tenant);

        Assert::assertSame(2, $result);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->doctrineRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with($this->identicalTo(File::class))
            ->willReturn($this->doctrineRepository);

        $this->repository = new FileRepository($this->entityManager);
    }

    private function createFile(?FileId $id = null, ?UserId $uploader = null, ?TenantId $tenant = null): File
    {
        $fileId = $id ?? FileId::fromString('00000000-1111-2222-3333-444444444444');
        $uploaderId = $uploader ?? UserId::fromString('55555555-6666-7777-8888-999999999999');

        return File::create(
            id: $fileId,
            name: FileName::fromString('document.pdf'),
            originalName: FileName::fromString('document.pdf'),
            mimeType: MimeType::fromString('application/pdf'),
            size: FileSize::fromBytes(1024),
            path: 'uploads/document.pdf',
            description: null,
            uploaderId: $uploaderId,
            tenantId: $tenant,
        );
    }
}
