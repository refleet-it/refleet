<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Application\Command\ProcessImage;

use App\File\File\Application\Command\ProcessImage\ProcessImageCommand;
use App\File\File\Application\Command\ProcessImage\ProcessImageHandler;
use App\File\File\Domain\Model\File;
use App\File\File\Domain\Repository\FileRepositoryInterface;
use App\File\File\Domain\Service\WebPImageStorageServiceInterface;
use App\File\File\Domain\ValueObject\FileId;
use App\Fixtures\Factory\File\FileFactory;
use DG\BypassFinals;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(ProcessImageHandler::class)]
#[UsesClass(ProcessImageCommand::class)]
#[UsesClass(File::class)]
final class ProcessImageHandlerTest extends TestCase
{
    use Factories;

    private FileRepositoryInterface&MockObject $fileRepository;

    private WebPImageStorageServiceInterface&MockObject $storageService;

    private LoggerInterface&MockObject $logger;

    private ProcessImageHandler $handler;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function invoke_updates_file_paths_and_deletes_original_after_successful_conversion(): void
    {
        // Arrange
        $file = FileFactory::new()->withoutPersisting()->create([
            'id' => FileId::fromString('11111111-2222-3333-4444-555555555555'),
            'path' => 'uploads/source/original.jpg',
        ]);
        $command = new ProcessImageCommand(
            fileId: $file->id(),
            originalPath: 'uploads/source/original.jpg',
            mimeType: 'image/jpeg',
        );

        $this->storageService
            ->expects($this->once())
            ->method('convertToWebP')
            ->with('uploads/source/original.jpg', 'image/jpeg')
            ->willReturn('uploads/webp/original.webp');

        $this->storageService
            ->expects($this->once())
            ->method('createWebPThumbnail')
            ->with('uploads/source/original.jpg', 'image/jpeg')
            ->willReturn('uploads/webp/thumbnails/original.webp');

        $this->fileRepository
            ->expects($this->once())
            ->method('findById')
            ->with($file->id())
            ->willReturn($file);

        $this->fileRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (File $savedFile): bool {
                Assert::assertSame('uploads/webp/original.webp', $savedFile->path());
                Assert::assertSame('uploads/webp/thumbnails/original.webp', $savedFile->thumbnailPath());

                return true;
            }));

        $this->storageService
            ->expects($this->once())
            ->method('delete')
            ->with('uploads/source/original.jpg');

        // Act
        ($this->handler)($command);

        // Assert
        Assert::assertSame('uploads/webp/original.webp', $file->path());
        Assert::assertSame('uploads/webp/thumbnails/original.webp', $file->thumbnailPath());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function invoke_returns_early_without_saving_when_file_does_not_exist(): void
    {
        // Arrange
        $fileId = FileId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $command = new ProcessImageCommand(
            fileId: $fileId,
            originalPath: 'uploads/source/missing.jpg',
            mimeType: 'image/jpeg',
        );

        $this->storageService
            ->expects($this->once())
            ->method('convertToWebP')
            ->willReturn('uploads/webp/missing.webp');

        $this->storageService
            ->expects($this->once())
            ->method('createWebPThumbnail')
            ->willReturn('uploads/webp/thumbnails/missing.webp');

        $this->fileRepository
            ->expects($this->once())
            ->method('findById')
            ->with($fileId)
            ->willReturn(null);

        $this->fileRepository
            ->expects($this->never())
            ->method('save');

        $this->storageService
            ->expects($this->never())
            ->method('delete');

        // Act
        ($this->handler)($command);

        // Assert
        Assert::assertSame('uploads/source/missing.jpg', $command->originalPath);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function invoke_does_not_delete_when_webp_path_equals_original_path(): void
    {
        // Arrange
        $file = FileFactory::new()->withoutPersisting()->create([
            'id' => FileId::fromString('99999999-8888-7777-6666-555555555555'),
            'path' => 'uploads/webp/already.webp',
        ]);
        $command = new ProcessImageCommand(
            fileId: $file->id(),
            originalPath: 'uploads/webp/already.webp',
            mimeType: 'image/webp',
        );

        $this->storageService
            ->expects($this->once())
            ->method('convertToWebP')
            ->willReturn('uploads/webp/already.webp');

        $this->storageService
            ->expects($this->once())
            ->method('createWebPThumbnail')
            ->willReturn(null);

        $this->fileRepository
            ->expects($this->once())
            ->method('findById')
            ->with($file->id())
            ->willReturn($file);

        $this->fileRepository
            ->expects($this->once())
            ->method('save')
            ->with($file);

        $this->storageService
            ->expects($this->never())
            ->method('delete');

        // Act
        ($this->handler)($command);

        // Assert
        Assert::assertSame('uploads/webp/already.webp', $file->path());
        Assert::assertNull($file->thumbnailPath());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function invoke_swallows_delete_errors_after_successful_file_update(): void
    {
        // Arrange
        $file = FileFactory::new()->withoutPersisting()->create([
            'id' => FileId::fromString('12345678-1234-1234-1234-123456789012'),
            'path' => 'uploads/source/photo.png',
        ]);
        $command = new ProcessImageCommand(
            fileId: $file->id(),
            originalPath: 'uploads/source/photo.png',
            mimeType: 'image/png',
        );

        $this->storageService
            ->expects($this->once())
            ->method('convertToWebP')
            ->willReturn('uploads/webp/photo.webp');

        $this->storageService
            ->expects($this->once())
            ->method('createWebPThumbnail')
            ->willReturn('uploads/webp/thumbnails/photo.webp');

        $this->fileRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($file);

        $this->fileRepository
            ->expects($this->once())
            ->method('save')
            ->with($file);

        $this->storageService
            ->expects($this->once())
            ->method('delete')
            ->with('uploads/source/photo.png')
            ->willThrowException(new \RuntimeException('Cannot delete'));

        // Act
        ($this->handler)($command);

        // Assert
        Assert::assertSame('uploads/webp/photo.webp', $file->path());
        Assert::assertSame('uploads/webp/thumbnails/photo.webp', $file->thumbnailPath());
    }

    #[Test]
    public function invoke_rethrows_and_logs_when_conversion_fails(): void
    {
        // Arrange
        $fileId = FileId::fromString('22222222-3333-4444-5555-666666666666');
        $command = new ProcessImageCommand(
            fileId: $fileId,
            originalPath: 'uploads/source/fail.jpg',
            mimeType: 'image/jpeg',
        );

        $this->storageService
            ->expects($this->once())
            ->method('convertToWebP')
            ->with('uploads/source/fail.jpg', 'image/jpeg')
            ->willThrowException(new \RuntimeException('Conversion failed'));

        $this->storageService
            ->expects($this->never())
            ->method('createWebPThumbnail');

        $this->fileRepository
            ->expects($this->never())
            ->method('findById');

        $this->fileRepository
            ->expects($this->never())
            ->method('save');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to process image',
                $this->callback(static function (array $context): bool {
                    Assert::assertArrayHasKey('fileId', $context);
                    Assert::assertArrayHasKey('error', $context);
                    Assert::assertSame('Conversion failed', $context['error']);
                    Assert::assertArrayHasKey('trace', $context);

                    return true;
                })
            );

        // Act
        try {
            ($this->handler)($command);
            Assert::fail('Expected RuntimeException to be thrown.');
        } catch (\RuntimeException $runtimeException) {
            // Assert
            Assert::assertSame('Conversion failed', $runtimeException->getMessage());
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        if (\class_exists(BypassFinals::class)) {
            BypassFinals::enable();
        }

        $this->fileRepository = $this->createMock(FileRepositoryInterface::class);
        $this->storageService = $this->createMock(WebPImageStorageServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new ProcessImageHandler($this->fileRepository, $this->storageService, $this->logger);
    }
}
