<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Infrastructure\Service;

use App\File\File\Domain\Repository\FileRepositoryInterface;
use App\File\File\Domain\Service\FileStorageServiceInterface;
use App\File\File\Domain\ValueObject\FileId;
use App\File\File\Domain\ValueObject\MimeType;
use App\File\File\Infrastructure\Service\ImageUrlResolver;
use App\Fixtures\Factory\File\FileFactory;
use App\Shared\Domain\ValueObject\ImageUrls;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(ImageUrlResolver::class)]
final class ImageUrlResolverTest extends TestCase
{
    use Factories;

    private FileRepositoryInterface&MockObject $fileRepository;

    private FileStorageServiceInterface&MockObject $fileStorageService;

    private ImageUrlResolver $resolver;

    #[Test]
    public function resolve_from_file_id_string_returns_empty_for_null_and_empty_input(): void
    {
        // Arrange
        $this->fileRepository
            ->expects($this->never())
            ->method('findById');

        $this->fileStorageService
            ->expects($this->never())
            ->method('getFileUrl');

        // Act
        $fromNull = $this->resolver->resolveFromFileIdString(null);
        $fromEmpty = $this->resolver->resolveFromFileIdString('');

        // Assert
        Assert::assertTrue($fromNull->isEmpty());
        Assert::assertSame('', $fromNull->url);
        Assert::assertNull($fromNull->thumbnailUrl);
        Assert::assertTrue($fromEmpty->isEmpty());
        Assert::assertSame('', $fromEmpty->url);
        Assert::assertNull($fromEmpty->thumbnailUrl);
    }

    #[Test]
    public function resolve_from_file_id_string_returns_empty_for_invalid_id_and_does_not_access_dependencies(): void
    {
        // Arrange
        $this->fileRepository
            ->expects($this->never())
            ->method('findById');

        $this->fileStorageService
            ->expects($this->never())
            ->method('getFileUrl');

        // Act
        $result = $this->resolver->resolveFromFileIdString('not-a-valid-uuid');

        // Assert
        Assert::assertTrue($result->isEmpty());
        Assert::assertSame('', $result->url);
        Assert::assertNull($result->thumbnailUrl);
    }

    #[Test]
    public function resolve_from_file_id_string_returns_empty_when_repository_does_not_find_file(): void
    {
        // Arrange
        $fileId = FileId::generate();

        $this->fileRepository
            ->expects($this->once())
            ->method('findById')
            ->with($this->callback(static fn (FileId $id): bool => $id->equals($fileId)))
            ->willReturn(null);

        $this->fileStorageService
            ->expects($this->never())
            ->method('getFileUrl');

        // Act
        $result = $this->resolver->resolveFromFileIdString($fileId->asString());

        // Assert
        Assert::assertTrue($result->isEmpty());
        Assert::assertSame('', $result->url);
        Assert::assertNull($result->thumbnailUrl);
    }

    #[Test]
    public function resolve_from_file_id_string_returns_empty_when_file_is_not_image(): void
    {
        // Arrange
        $file = FileFactory::new()->withoutPersisting()->create([
            'mimeType' => MimeType::fromString('application/pdf'),
        ]);

        $this->fileRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($file);

        $this->fileStorageService
            ->expects($this->never())
            ->method('getFileUrl');

        // Act
        $result = $this->resolver->resolveFromFileIdString($file->id()->asString());

        // Assert
        Assert::assertTrue($result->isEmpty());
        Assert::assertSame('', $result->url);
        Assert::assertNull($result->thumbnailUrl);
    }

    #[Test]
    public function resolve_from_file_id_string_returns_main_and_thumbnail_urls_for_image_file_with_thumbnail(): void
    {
        // Arrange
        $file = FileFactory::new()->withoutPersisting()->create([
            'mimeType' => MimeType::fromString('image/png'),
            'path' => 'uploads/source/avatar.png',
        ]);
        $file->setThumbnailPath('uploads/thumb/avatar.webp');

        $this->fileRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($file);

        $this->fileStorageService
            ->expects($this->exactly(2))
            ->method('getFileUrl')
            ->willReturnMap([
                ['uploads/source/avatar.png', 'https://cdn.example.com/source/avatar.png'],
                ['uploads/thumb/avatar.webp', 'https://cdn.example.com/thumb/avatar.webp'],
            ]);

        // Act
        $result = $this->resolver->resolveFromFileIdString($file->id()->asString());

        // Assert
        Assert::assertInstanceOf(ImageUrls::class, $result);
        Assert::assertFalse($result->isEmpty());
        Assert::assertSame('https://cdn.example.com/source/avatar.png', $result->url);
        Assert::assertSame('https://cdn.example.com/thumb/avatar.webp', $result->thumbnailUrl);
    }

    #[Test]
    public function resolve_from_file_id_string_returns_main_url_and_null_thumbnail_for_image_without_thumbnail_path(): void
    {
        // Arrange
        $file = FileFactory::new()->withoutPersisting()->create([
            'mimeType' => MimeType::fromString('image/webp'),
            'path' => 'uploads/source/photo.webp',
        ]);

        $this->fileRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($file);

        $this->fileStorageService
            ->expects($this->once())
            ->method('getFileUrl')
            ->with('uploads/source/photo.webp')
            ->willReturn('https://cdn.example.com/source/photo.webp');

        // Act
        $result = $this->resolver->resolveFromFileIdString($file->id()->asString());

        // Assert
        Assert::assertFalse($result->isEmpty());
        Assert::assertSame('https://cdn.example.com/source/photo.webp', $result->url);
        Assert::assertNull($result->thumbnailUrl);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->fileRepository = $this->createMock(FileRepositoryInterface::class);
        $this->fileStorageService = $this->createMock(FileStorageServiceInterface::class);
        $this->resolver = new ImageUrlResolver($this->fileRepository, $this->fileStorageService);
    }
}
