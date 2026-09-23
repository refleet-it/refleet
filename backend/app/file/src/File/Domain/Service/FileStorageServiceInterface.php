<?php

declare(strict_types=1);

namespace App\File\File\Domain\Service;

use App\File\File\Domain\Exception\FileStorageException;
use App\File\File\Domain\ValueObject\FileId;
use App\File\File\Domain\ValueObject\FileName;
use App\File\File\Domain\ValueObject\MimeType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface FileStorageServiceInterface
{
    /**
     * @throws FileStorageException
     */
    public function store(UploadedFile $uploadedFile, FileId $fileId, FileName $fileName): string;

    public function getThumbnailUrl(string $path): ?string;

    public function getFileUrl(string $path): string;

    public function delete(string $path): void;

    public function getContent(string $path): string;

    /**
     * @return resource
     *
     * @throws FileStorageException
     */
    public function getStream(string $path): mixed;

    public function createThumbnail(string $path, MimeType $mimeType): ?string;

    public function getThumbnailContent(string $path): ?string;

    /**
     * @return resource|null
     */
    public function getThumbnailStream(string $path): mixed;
}
