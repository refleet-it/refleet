<?php

declare(strict_types=1);

namespace App\File\File\Infrastructure\Service;

use App\File\File\Domain\Exception\FileStorageException;
use App\File\File\Domain\Service\FileStorageServiceInterface;
use App\File\File\Domain\Service\WebPImageStorageServiceInterface;
use App\File\File\Domain\ValueObject\FileId;
use App\File\File\Domain\ValueObject\FileName;
use App\File\File\Domain\ValueObject\MimeType;
use App\Shared\Domain\Service\StorageAvailabilityCheckerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class FileStorageService implements FileStorageServiceInterface, WebPImageStorageServiceInterface, StorageAvailabilityCheckerInterface
{
    private const string THUMBNAIL_DIR = 'thumbnails';

    private const string WEBP_DIR = 'webp';

    private const int MAX_THUMBNAIL_SIZE = 300;

    private const int DIR_PERMISSION = 0o755;

    private const int IMAGE_QUALITY_JPEG = 90;

    private const int IMAGE_COMPRESSION_PNG = 9;

    private const int WEBP_QUALITY = 88;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads')]
        private string $uploadPath,
        #[Autowire('%env(APP_URL)%')]
        private string $baseUrl,
    ) {
        $this->ensureDirectoriesExist();
    }

    /**
     * @throws FileStorageException
     */
    #[\Override]
    public function store(UploadedFile $uploadedFile, FileId $fileId, FileName $fileName): string
    {
        try {
            $path = $this->generateFilePath($fileId, $fileName);
            $fullPath = $this->uploadPath.'/'.$path;

            $uploadedFile->move(
                \dirname($fullPath),
                \basename($fullPath)
            );

            return $path;
        } catch (\Exception $exception) {
            throw new FileStorageException('Failed to store file: '.$exception->getMessage(), 0, $exception);
        }
    }

    #[\Override]
    public function getThumbnailUrl(string $path): ?string
    {
        $thumbnailPath = $this->getThumbnailPath($path);
        $fullThumbnailPath = $this->uploadPath.'/'.$thumbnailPath;

        if (!\file_exists($fullThumbnailPath)) {
            return null;
        }

        return $this->getFileUrl($thumbnailPath);
    }

    #[\Override]
    public function getFileUrl(string $path): string
    {
        // If path already starts with 'uploads/', don't add another 'uploads/'
        if (\str_starts_with($path, 'uploads/')) {
            return $this->baseUrl.'/'.$path;
        }

        return $this->baseUrl.'/uploads/'.$path;
    }

    #[\Override]
    public function delete(string $path): void
    {
        try {
            $fullPath = $this->uploadPath.'/'.$path;
            if (\file_exists($fullPath)) {
                \unlink($fullPath);
            }

            $thumbnailPath = $this->getThumbnailPath($path);
            if (\file_exists($thumbnailPath)) {
                \unlink($thumbnailPath);
            }
        } catch (\Exception $exception) {
            throw new FileStorageException('Failed to delete file: '.$exception->getMessage(), 0, $exception);
        }
    }

    #[\Override]
    public function getContent(string $path): string
    {
        $fullPath = $this->uploadPath.'/'.$path;

        if (!\file_exists($fullPath)) {
            throw new FileStorageException('File not found: '.$path);
        }

        $content = \file_get_contents($fullPath);
        if (false === $content) {
            throw new FileStorageException('Failed to read file: '.$path);
        }

        return $content;
    }

    /**
     * @return resource
     *
     * @throws FileStorageException
     */
    #[\Override]
    public function getStream(string $path): mixed
    {
        $fullPath = $this->uploadPath.'/'.$path;

        if (!\file_exists($fullPath)) {
            throw new FileStorageException('File not found: '.$path);
        }

        $stream = \fopen($fullPath, 'r');
        if (false === $stream) {
            throw new FileStorageException('Failed to open file: '.$path);
        }

        return $stream;
    }

    #[\Override]
    public function createThumbnail(string $path, MimeType $mimeType): ?string
    {
        if (!$mimeType->isImage()) {
            return null;
        }

        try {
            $fullPath = $this->uploadPath.'/'.$path;
            $thumbnailPath = $this->getThumbnailPath($path);

            if (!\file_exists($fullPath)) {
                return null;
            }

            $this->ensureThumbnailDirectoryExists();

            $image = $this->createImageFromFile($fullPath, $mimeType);
            if (null === $image) {
                return null;
            }

            $this->resizeImage($image, self::MAX_THUMBNAIL_SIZE);
            $this->saveImage($image, $thumbnailPath, $mimeType);

            return $this->getThumbnailPath($path);
        } catch (\Exception) {
            return null;
        }
    }

    #[\Override]
    public function getThumbnailContent(string $path): ?string
    {
        $thumbnailPath = $this->getThumbnailPath($path);

        if (!\file_exists($thumbnailPath)) {
            return null;
        }

        $content = \file_get_contents($thumbnailPath);
        if (false === $content) {
            return null;
        }

        return $content;
    }

    /**
     * @return resource|null
     */
    #[\Override]
    public function getThumbnailStream(string $path): mixed
    {
        $thumbnailPath = $this->getThumbnailPath($path);

        if (!\file_exists($thumbnailPath)) {
            return null;
        }

        $stream = \fopen($thumbnailPath, 'r');
        if (false === $stream) {
            return null;
        }

        return $stream;
    }

    #[\Override]
    public function convertToWebP(string $originalPath, string $mimeType): ?string
    {
        $mimeTypeObj = MimeType::fromString($mimeType);

        if (!$mimeTypeObj->isImage() || 'image/webp' === $mimeType) {
            return $originalPath;
        }

        try {
            $fullPath = $this->uploadPath.'/'.$originalPath;

            if (!\file_exists($fullPath)) {
                return null;
            }

            $image = $this->createImageFromFile($fullPath, $mimeTypeObj);
            if (null === $image) {
                return null;
            }

            $webpPath = $this->getWebpPath($originalPath);
            $this->ensureWebpDirectoryExists();
            $fullWebpPath = $this->uploadPath.'/'.$webpPath;

            \imagewebp($image, $fullWebpPath, self::WEBP_QUALITY);

            return $webpPath;
        } catch (\Exception) {
            return null;
        }
    }

    #[\Override]
    public function createWebPThumbnail(string $originalPath, string $mimeType): ?string
    {
        $mimeTypeObj = MimeType::fromString($mimeType);

        if (!$mimeTypeObj->isImage()) {
            return null;
        }

        try {
            $fullPath = $this->uploadPath.'/'.$originalPath;

            if (!\file_exists($fullPath)) {
                return null;
            }

            $image = $this->createImageFromFile($fullPath, $mimeTypeObj);
            if (null === $image) {
                return null;
            }

            $this->resizeImage($image, self::MAX_THUMBNAIL_SIZE);

            $thumbPath = $this->getWebpThumbnailPath($originalPath);
            $this->ensureWebpDirectoryExists();
            $fullThumbPath = $this->uploadPath.'/'.$thumbPath;

            \imagewebp($image, $fullThumbPath, self::WEBP_QUALITY);

            return $thumbPath;
        } catch (\Exception) {
            return null;
        }
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return \is_dir($this->uploadPath) && \is_writable($this->uploadPath);
    }

    private function ensureDirectoriesExist(): void
    {
        if (!\is_dir($this->uploadPath)) {
            \mkdir($this->uploadPath, self::DIR_PERMISSION, true);
        }
    }

    private function generateFilePath(FileId $fileId, FileName $fileName): string
    {
        $extension = $fileName->getExtension();
        $extension = '' !== $extension ? '.'.$extension : '';

        return $fileId->asString().$extension;
    }

    private function getThumbnailPath(string $path): string
    {
        $filename = \basename($path);
        $extension = \pathinfo($filename, \PATHINFO_EXTENSION);
        $nameWithoutExt = \pathinfo($filename, \PATHINFO_FILENAME);

        return self::THUMBNAIL_DIR.'/'.$nameWithoutExt.'_thumb.'.$extension;
    }

    private function ensureThumbnailDirectoryExists(): void
    {
        $thumbnailDir = $this->uploadPath.'/'.self::THUMBNAIL_DIR;
        if (!\is_dir($thumbnailDir)) {
            \mkdir($thumbnailDir, self::DIR_PERMISSION, true);
        }
    }

    private function createImageFromFile(string $path, MimeType $mimeType): ?\GdImage
    {
        $image = match ($mimeType->asString()) {
            'image/jpeg' => \imagecreatefromjpeg($path),
            'image/png' => \imagecreatefrompng($path),
            'image/gif' => \imagecreatefromgif($path),
            'image/webp' => \imagecreatefromwebp($path),
            default => null,
        };

        return false === $image ? null : $image;
    }

    private function resizeImage(\GdImage $image, int $maxSize): void
    {
        $width = \imagesx($image);
        $height = \imagesy($image);

        if ($width <= $maxSize && $height <= $maxSize) {
            return;
        }

        $ratio = \min($maxSize / $width, $maxSize / $height);
        $newWidthInt = \max(1, (int) ($width * $ratio));
        $newHeightInt = \max(1, (int) ($height * $ratio));

        $resized = \imagecreatetruecolor($newWidthInt, $newHeightInt);

        \imagealphablending($resized, false);
        \imagesavealpha($resized, true);

        \imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidthInt, $newHeightInt, $width, $height);
        $image = $resized;
    }

    private function saveImage(\GdImage $image, string $path, MimeType $mimeType): void
    {
        match ($mimeType->asString()) {
            'image/jpeg' => \imagejpeg($image, $path, self::IMAGE_QUALITY_JPEG),
            'image/png' => \imagepng($image, $path, self::IMAGE_COMPRESSION_PNG),
            'image/gif' => \imagegif($image, $path),
            'image/webp' => \imagewebp($image, $path, self::IMAGE_QUALITY_JPEG),
            default => throw new FileStorageException('Unsupported image format for thumbnail'),
        };
    }

    private function getWebpPath(string $originalPath): string
    {
        $nameWithoutExt = \pathinfo(\basename($originalPath), \PATHINFO_FILENAME);

        return $nameWithoutExt.'.webp';
    }

    private function getWebpThumbnailPath(string $originalPath): string
    {
        $nameWithoutExt = \pathinfo(\basename($originalPath), \PATHINFO_FILENAME);

        return self::WEBP_DIR.'/'.$nameWithoutExt.'_thumb.webp';
    }

    private function ensureWebpDirectoryExists(): void
    {
        $webpDir = $this->uploadPath.'/'.self::WEBP_DIR;
        if (!\is_dir($webpDir)) {
            \mkdir($webpDir, self::DIR_PERMISSION, true);
        }
    }
}
