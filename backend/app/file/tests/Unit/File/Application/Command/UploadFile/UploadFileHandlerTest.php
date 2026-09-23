<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Application\Command\UploadFile;

use App\File\File\Application\Command\ProcessImage\ProcessImageCommand;
use App\File\File\Application\Command\UploadFile\UploadFileCommand;
use App\File\File\Application\Command\UploadFile\UploadFileHandler;
use App\File\File\Domain\Model\File;
use App\File\File\Domain\Repository\FileRepositoryInterface;
use App\File\File\Domain\Service\FileStorageServiceInterface;
use App\File\File\Domain\ValueObject\FileId;
use App\File\File\Domain\ValueObject\FileName;
use App\File\File\Domain\ValueObject\FileSize;
use App\File\File\Domain\ValueObject\MimeType;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class UploadFileHandlerTest extends TestCase
{
    private FileRepositoryInterface&MockObject $fileRepository;

    private FileStorageServiceInterface&MockObject $fileStorage;

    private MessageBusInterface&MockObject $messageBus;

    private UploadFileHandler $handler;

    #[Test]
    public function saves_file_with_generated_path_and_correct_data(): void
    {
        // Arrange
        $fileId = FileId::fromString('11111111-2222-3333-4444-555555555555');
        $uploadedFile = $this->createStub(UploadedFile::class);
        $uploadedFile->method('getClientOriginalExtension')->willReturn('jpg');

        $fileName = FileName::fromString('photo.jpg');
        $originalName = FileName::fromString('original-name.jpg');
        $mimeType = MimeType::fromString('image/jpeg');
        $size = FileSize::fromBytes(12345);
        $description = 'Test image';
        $uploaderId = UserId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $tenantId = TenantId::fromString('ffffffff-1111-2222-3333-444444444444');

        $expectedPath = 'uploads/11111111222233334444555555555555.jpg';

        $this->fileStorage
            ->expects($this->once())
            ->method('store')
            ->with($uploadedFile, $fileId, $fileName)
            ->willReturn($expectedPath);

        $command = new UploadFileCommand(
            fileId: $fileId,
            uploadedFile: $uploadedFile,
            fileName: $fileName,
            originalName: $originalName,
            mimeType: $mimeType,
            size: $size,
            description: $description,
            uploaderId: $uploaderId,
            tenantId: $tenantId,
        );

        $this->fileRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (File $file) use ($fileId, $fileName, $originalName, $mimeType, $size, $description, $uploaderId, $tenantId, $expectedPath): bool {
                $this->assertTrue($file->id()->equals($fileId));
                $this->assertSame($fileName->asString(), $file->name()->asString());
                $this->assertSame($originalName->asString(), $file->originalName()->asString());
                $this->assertTrue($file->mimeType()->equals($mimeType));
                $this->assertTrue($file->size()->equals($size));
                $this->assertSame($description, $file->description());
                $this->assertSame($uploaderId->asString(), $file->uploaderId()->asString());
                $this->assertSame($tenantId->asString(), $file->tenantId()?->asString());

                $this->assertSame($expectedPath, $file->path());

                return true;
            }));

        // Expect async image processing for image files
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (ProcessImageCommand $command) use ($fileId, $expectedPath, $mimeType): bool {
                $this->assertTrue($command->fileId->equals($fileId));
                $this->assertSame($expectedPath, $command->originalPath);
                $this->assertSame($mimeType->asString(), $command->mimeType);

                return true;
            }))
            ->willReturnCallback(static fn ($message) => new Envelope($message));

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function generates_path_without_extension_when_missing(): void
    {
        // Arrange
        $fileId = FileId::fromString('22222222-3333-4444-5555-666666666666');
        $uploadedFile = $this->createStub(UploadedFile::class);
        $uploadedFile->method('getClientOriginalExtension')->willReturn('');

        $fileName = FileName::fromString('document');
        $originalName = FileName::fromString('doc');
        $mimeType = MimeType::fromString('application/pdf');
        $size = FileSize::fromBytes(2048);
        $description = null;
        $uploaderId = UserId::fromString('12121212-3434-5656-7878-909090909090');
        $tenantId = null;

        $expectedPath = 'uploads/22222222333344445555666666666666';

        $this->fileStorage
            ->expects($this->once())
            ->method('store')
            ->with($uploadedFile, $fileId, $fileName)
            ->willReturn($expectedPath);

        $command = new UploadFileCommand(
            fileId: $fileId,
            uploadedFile: $uploadedFile,
            fileName: $fileName,
            originalName: $originalName,
            mimeType: $mimeType,
            size: $size,
            description: $description,
            uploaderId: $uploaderId,
            tenantId: $tenantId,
        );

        $this->fileRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (File $file) use ($fileId, $fileName, $originalName, $mimeType, $size, $uploaderId, $expectedPath): bool {
                $this->assertTrue($file->id()->equals($fileId));
                $this->assertSame($fileName->asString(), $file->name()->asString());
                $this->assertSame($originalName->asString(), $file->originalName()->asString());
                $this->assertTrue($file->mimeType()->equals($mimeType));
                $this->assertTrue($file->size()->equals($size));
                $this->assertNull($file->description());
                $this->assertSame($uploaderId->asString(), $file->uploaderId()->asString());
                $this->assertNotInstanceOf(TenantId::class, $file->tenantId());

                $this->assertSame($expectedPath, $file->path());

                return true;
            }));

        // No async image processing for non-image files
        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        // Act
        ($this->handler)($command);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->fileRepository = $this->createMock(FileRepositoryInterface::class);
        $this->fileStorage = $this->createMock(FileStorageServiceInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->handler = new UploadFileHandler($this->fileRepository, $this->fileStorage, $this->messageBus);
    }
}
