<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Infrastructure\Service;

use App\File\File\Domain\Exception\FileStorageException;
use App\File\File\Domain\ValueObject\FileId;
use App\File\File\Domain\ValueObject\FileName;
use App\File\File\Domain\ValueObject\MimeType;
use App\File\File\Infrastructure\Service\FileStorageService;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[CoversClass(FileStorageService::class)]
#[UsesClass(FileId::class)]
#[UsesClass(FileName::class)]
#[UsesClass(MimeType::class)]
#[UsesClass(FileStorageException::class)]
final class FileStorageServiceTest extends TestCase
{
    private string $uploadDir;

    private FileStorageService $service;

    #[Test]
    public function store_moves_uploaded_file_and_returns_relative_path(): void
    {
        // Arrange
        $source = \tempnam(\sys_get_temp_dir(), 'upload_source_');
        \file_put_contents($source, 'stored-content');
        $uploadedFile = new UploadedFile(
            $source,
            'example.jpg',
            'image/jpeg',
            null,
            true,
        );

        $fileId = FileId::fromString('00000000-0000-0000-0000-000000000001');
        $fileName = FileName::fromString('example.jpg');

        // Act
        $relativePath = $this->service->store($uploadedFile, $fileId, $fileName);

        // Assert
        Assert::assertSame($fileId->asString().'.jpg', $relativePath);
        $storedPath = $this->uploadDir.'/'.$relativePath;
        Assert::assertFileExists($storedPath);
        Assert::assertSame('stored-content', \file_get_contents($storedPath));
    }

    #[Test]
    public function store_wraps_exceptions_in_file_storage_exception(): void
    {
        // Arrange
        /** @var UploadedFile&MockObject $uploaded */
        $uploaded = $this->createMock(UploadedFile::class);
        $uploaded
            ->expects($this->once())
            ->method('move')
            ->willThrowException(new \RuntimeException('failure'));

        $fileId = FileId::fromString('00000000-0000-0000-0000-000000000002');
        $fileName = FileName::fromString('broken.txt');

        // Act & Assert
        $this->expectException(FileStorageException::class);
        $this->expectExceptionMessage('Failed to store file: failure');
        $this->service->store($uploaded, $fileId, $fileName);
    }

    #[Test]
    public function get_file_url_applies_uploads_prefix_when_missing(): void
    {
        // Act
        $url = $this->service->getFileUrl('folder/file.pdf');

        // Assert
        Assert::assertSame('http://localhost/uploads/folder/file.pdf', $url);
    }

    #[Test]
    public function get_file_url_preserves_existing_uploads_prefix(): void
    {
        // Act
        $url = $this->service->getFileUrl('uploads/already/there.png');

        // Assert
        Assert::assertSame('http://localhost/uploads/already/there.png', $url);
    }

    #[Test]
    public function get_thumbnail_url_returns_null_when_thumbnail_not_found(): void
    {
        // Act
        $url = $this->service->getThumbnailUrl('photo.jpg');

        // Assert
        Assert::assertNull($url);
    }

    #[Test]
    public function get_thumbnail_url_returns_prefixed_path_when_thumbnail_exists(): void
    {
        // Arrange
        $thumbnailDir = $this->uploadDir.'/thumbnails';
        \mkdir($thumbnailDir, 0777, true);
        $thumbnailPath = $thumbnailDir.'/photo_thumb.jpg';
        \file_put_contents($thumbnailPath, 'thumb');

        // Act
        $url = $this->service->getThumbnailUrl('photo.jpg');

        // Assert
        Assert::assertSame('http://localhost/uploads/thumbnails/photo_thumb.jpg', $url);
    }

    #[Test]
    public function delete_removes_existing_file(): void
    {
        // Arrange
        $path = 'document.pdf';
        $fullPath = $this->uploadDir.'/'.$path;
        \file_put_contents($fullPath, 'to-delete');
        Assert::assertFileExists($fullPath);

        // Act
        $this->service->delete($path);

        // Assert
        Assert::assertFileDoesNotExist($fullPath);
    }

    #[Test]
    public function get_content_returns_file_contents(): void
    {
        // Arrange
        $path = 'notes.txt';
        $fullPath = $this->uploadDir.'/'.$path;
        \file_put_contents($fullPath, 'content here');

        // Act
        $content = $this->service->getContent($path);

        // Assert
        Assert::assertSame('content here', $content);
    }

    #[Test]
    public function get_content_throws_when_file_missing(): void
    {
        // Act & Assert
        $this->expectException(FileStorageException::class);
        $this->expectExceptionMessage('File not found: missing.txt');
        $this->service->getContent('missing.txt');
    }

    #[Test]
    public function get_stream_returns_resource_for_existing_file(): void
    {
        // Arrange
        $path = 'data.json';
        $fullPath = $this->uploadDir.'/'.$path;
        \file_put_contents($fullPath, \json_encode(['foo' => 'bar'], \JSON_THROW_ON_ERROR));

        // Act
        $stream = $this->service->getStream($path);

        // Assert
        Assert::assertIsResource($stream);
        Assert::assertSame('{"foo":"bar"}', \stream_get_contents($stream));
        \fclose($stream);
    }

    #[Test]
    public function get_stream_throws_when_file_missing(): void
    {
        // Act & Assert
        $this->expectException(FileStorageException::class);
        $this->expectExceptionMessage('File not found: unknown.bin');
        $this->service->getStream('unknown.bin');
    }

    #[Test]
    public function create_thumbnail_returns_null_for_non_image_mime_type(): void
    {
        // Act
        $result = $this->service->createThumbnail('doc.pdf', MimeType::fromString('application/pdf'));

        // Assert
        Assert::assertNull($result);
    }

    #[Test]
    public function get_thumbnail_content_returns_null_when_not_found(): void
    {
        // Act
        $content = $this->service->getThumbnailContent('file.png');

        // Assert
        Assert::assertNull($content);
    }

    #[Test]
    public function get_thumbnail_stream_returns_null_when_not_found(): void
    {
        // Act
        $stream = $this->service->getThumbnailStream('file.png');

        // Assert
        Assert::assertNull($stream);
    }

    #[Test]
    public function is_available_returns_true_when_upload_dir_is_writable(): void
    {
        // Act & Assert
        Assert::assertTrue($this->service->isAvailable());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->uploadDir = \sys_get_temp_dir().'/refleet_file_storage_'.\bin2hex(\random_bytes(6));
        \mkdir($this->uploadDir, 0777, true);
        $this->service = new FileStorageService($this->uploadDir, 'http://localhost');
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
            \RecursiveIteratorIterator::CHILD_FIRST,
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
