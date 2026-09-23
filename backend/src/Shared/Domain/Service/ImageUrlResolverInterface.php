<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

use App\Shared\Domain\ValueObject\ImageUrls;

/**
 * Interface for resolving image URLs from file IDs.
 *
 * This interface allows Business context to resolve image URLs without
 * directly depending on File context implementation.
 */
interface ImageUrlResolverInterface
{
    /**
     * Resolve image URLs from a file ID string.
     *
     * Returns empty ImageUrls if fileId is invalid, file doesn't exist, or is not an image.
     */
    public function resolveFromFileIdString(?string $fileIdString): ImageUrls;
}
