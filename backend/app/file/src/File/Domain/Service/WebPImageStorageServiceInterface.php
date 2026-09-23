<?php

declare(strict_types=1);

namespace App\File\File\Domain\Service;

interface WebPImageStorageServiceInterface
{
    public function convertToWebP(string $originalPath, string $mimeType): ?string;

    public function createWebPThumbnail(string $originalPath, string $mimeType): ?string;

    public function delete(string $path): void;
}
