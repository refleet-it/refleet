<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Application\Command\DeleteFile;

use App\File\File\Application\Command\DeleteFile\DeleteFileCommand;
use App\File\File\Application\Command\DeleteFile\DeleteFileHandler;
use App\File\File\Domain\Exception\FileAccessDeniedException;
use App\File\File\Domain\Exception\FileNotFoundException;
use App\File\File\Domain\Model\File;
use App\File\File\Domain\Repository\FileRepositoryInterface;
use App\File\File\Domain\ValueObject\FileId;
use App\File\File\Domain\ValueObject\FileName;
use App\File\File\Domain\ValueObject\FileSize;
use App\File\File\Domain\ValueObject\MimeType;
use App\File\File\Infrastructure\Service\FileStorageService;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteFileHandler::class)]
#[UsesClass(DeleteFileCommand::class)]
#[UsesClass(File::class)]
#[UsesClass(FileId::class)]
#[UsesClass(FileName::class)]
#[UsesClass(FileSize::class)]
#[UsesClass(MimeType::class)]
#[UsesClass(UserId::class)]
#[UsesClass(TenantId::class)]
final class DeleteFileHandlerTest extends TestCase
{
    private FileRepositoryInterface&MockObject $files;

    private DeleteFileHandler $handler;

    private string $uploadDir;

    #[Test]
    public function deletes_file_and_marks_entity_deleted(): void
    {
        // Arrange
        $fileId = FileId::fromString('11111111-2222-3333-4444-555555555555');
        $uploaderId = UserId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');

        $file = File::create(
            id: $fileId,
            name: FileName::fromString('photo.jpg'),
            originalName: FileName::fromString('original.jpg'),
            mimeType: MimeType::fromString('image/jpeg'),
            size: FileSize::fromBytes(1024),
            path: 'uploads/to-delete.jpg',
            description: null,
            uploaderId: $uploaderId,
            tenantId: null,
        );

        // create actual file on disk to verify deletion side-effect
        $fullPath = $this->uploadDir.'/uploads/to-delete.jpg';
        @\mkdir(\dirname($fullPath), 0777, true);
        \file_put_contents($fullPath, 'content');
        Assert::assertFileExists($fullPath);

        $this->files
            ->expects($this->once())
            ->method('findById')
            ->with($this->identicalTo($fileId))
            ->willReturn($file);

        $this->files
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (File $saved) use ($fileId): bool {
                Assert::assertSame($fileId->asString(), $saved->id()->asString());
                Assert::assertTrue($saved->isDeleted());

                return true;
            }));

        $command = new DeleteFileCommand($fileId, $uploaderId);

        // Act
        ($this->handler)($command);

        // Assert
        Assert::assertFileDoesNotExist($fullPath);
    }

    #[Test]
    public function throws_when_file_not_found(): void
    {
        // Arrange
        $fileId = FileId::fromString('22222222-3333-4444-5555-666666666666');
        $uploaderId = UserId::fromString('bbbbbbbb-cccc-dddd-eeee-ffffffffffff');
        $command = new DeleteFileCommand($fileId, $uploaderId);

        $this->files
            ->expects($this->once())
            ->method('findById')
            ->with($this->identicalTo($fileId))
            ->willReturn(null);

        $this->files->expects($this->never())->method('save');

        // Act & Assert
        $this->expectException(FileNotFoundException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function throws_when_access_denied(): void
    {
        // Arrange
        $fileId = FileId::fromString('33333333-4444-5555-6666-777777777777');
        $ownerId = UserId::fromString('12121212-3434-5656-7878-909090909090');
        $actorId = UserId::fromString('99999999-8888-7777-6666-555555555555');

        $file = File::create(
            id: $fileId,
            name: FileName::fromString('doc.pdf'),
            originalName: FileName::fromString('doc.pdf'),
            mimeType: MimeType::fromString('application/pdf'),
            size: FileSize::fromBytes(2048),
            path: 'uploads/some-doc.pdf',
            description: null,
            uploaderId: $ownerId,
            tenantId: TenantId::fromString('aaaaaaaa-0000-0000-0000-000000000000'),
        );

        $this->files
            ->expects($this->once())
            ->method('findById')
            ->with($this->identicalTo($fileId))
            ->willReturn($file);

        $this->files->expects($this->never())->method('save');

        $command = new DeleteFileCommand($fileId, $actorId);

        // Act & Assert
        $this->expectException(FileAccessDeniedException::class);
        ($this->handler)($command);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->files = $this->createMock(FileRepositoryInterface::class);

        // create isolated temp upload directory for FileStorageService
        $this->uploadDir = \sys_get_temp_dir().'/refleet_uploads_'.\bin2hex(\random_bytes(6));
        \mkdir($this->uploadDir, 0777, true);
        $storage = new FileStorageService($this->uploadDir, 'http://localhost');

        $this->handler = new DeleteFileHandler($this->files, $storage);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDir($this->uploadDir);
    }

    private function removeDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @\rmdir($item->getPathname());
            } else {
                @\unlink($item->getPathname());
            }
        }

        @\rmdir($dir);
    }
}
