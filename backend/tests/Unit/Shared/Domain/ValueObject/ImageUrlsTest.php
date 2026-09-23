<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\ImageUrls;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageUrls::class)]
final class ImageUrlsTest extends TestCase
{
    #[Test]
    public function empty_factory_returns_empty_image_urls(): void
    {
        // Arrange

        // Act
        $imageUrls = ImageUrls::empty();

        // Assert
        Assert::assertSame('', $imageUrls->url);
        Assert::assertNull($imageUrls->thumbnailUrl);
        Assert::assertTrue($imageUrls->isEmpty());
    }

    #[Test]
    public function non_empty_url_is_not_empty_even_without_thumbnail(): void
    {
        // Arrange
        $url = 'https://cdn.example.com/images/photo.webp';

        // Act
        $imageUrls = new ImageUrls($url, null);

        // Assert
        Assert::assertSame($url, $imageUrls->url);
        Assert::assertNull($imageUrls->thumbnailUrl);
        Assert::assertFalse($imageUrls->isEmpty());
    }

    #[Test]
    public function emptiness_depends_only_on_main_url_value(): void
    {
        // Arrange
        $thumbnailUrl = 'https://cdn.example.com/images/photo-thumb.webp';

        // Act
        $imageUrls = new ImageUrls('', $thumbnailUrl);

        // Assert
        Assert::assertSame('', $imageUrls->url);
        Assert::assertSame($thumbnailUrl, $imageUrls->thumbnailUrl);
        Assert::assertTrue($imageUrls->isEmpty());
    }
}
