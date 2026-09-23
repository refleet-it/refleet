<?php

declare(strict_types=1);

namespace App\File\File\Infrastructure\Service;

use App\File\File\Domain\Model\File;
use App\File\File\Domain\Repository\FileRepositoryInterface;
use App\File\File\Domain\Service\FileStorageServiceInterface;
use App\File\File\Domain\ValueObject\FileId;
use App\Shared\Domain\Service\ImageUrlResolverInterface;
use App\Shared\Domain\ValueObject\ImageUrls;

final readonly class ImageUrlResolver implements ImageUrlResolverInterface
{
    public function __construct(
        private FileRepositoryInterface $fileRepository,
        private FileStorageServiceInterface $fileStorageService,
    ) {
    }

    /**
     * Resolve image URLs from a file ID.
     *
     * Returns empty ImageUrls if file doesn't exist or is not an image.
     */
    public function resolveFromFileId(?FileId $fileId): ImageUrls
    {
        if (null === $fileId) {
            return ImageUrls::empty();
        }

        $file = $this->fileRepository->findById($fileId);

        if (null === $file) {
            return ImageUrls::empty();
        }

        return $this->resolveFromFile($file);
    }

    /**
     * Resolve image URLs from a File entity.
     *
     * Returns empty ImageUrls if file is not an image.
     */
    public function resolveFromFile(File $file): ImageUrls
    {
        if (!$file->mimeType()->isImage()) {
            return ImageUrls::empty();
        }

        $url = $this->fileStorageService->getFileUrl($file->path());

        $thumbnailUrl = null;
        $thumbnailPath = $file->thumbnailPath();
        if (null !== $thumbnailPath) {
            $thumbnailUrl = $this->fileStorageService->getFileUrl($thumbnailPath);
        }

        return new ImageUrls($url, $thumbnailUrl);
    }

    /**
     * Resolve image URLs from a file ID string.
     *
     * Returns empty ImageUrls if fileId is invalid, file doesn't exist, or is not an image.
     */
    #[\Override]
    public function resolveFromFileIdString(?string $fileIdString): ImageUrls
    {
        if (null === $fileIdString) {
            return ImageUrls::empty();
        }

        if ('' === $fileIdString) {
            return ImageUrls::empty();
        }

        try {
            $fileId = FileId::fromString($fileIdString);

            return $this->resolveFromFileId($fileId);
        } catch (\InvalidArgumentException) {
            return ImageUrls::empty();
        }
    }
}
