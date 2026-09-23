<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * DTO representing image URLs with all variants.
 *
 * All images are stored as WebP for optimal size and quality.
 */
final readonly class ImageUrls
{
    public function __construct(
        public string $url,
        public ?string $thumbnailUrl,
    ) {
    }

    /**
     * Create ImageUrls from a file ID when file doesn't exist.
     */
    public static function empty(): self
    {
        return new self('', null);
    }

    public function isEmpty(): bool
    {
        return '' === $this->url;
    }
}
