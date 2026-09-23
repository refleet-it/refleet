<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Twig;

use App\Shared\Infrastructure\Twig\AppUrlTwigExtension;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AppUrlTwigExtension::class)]
final class AppUrlTwigExtensionTest extends TestCase
{
    #[Test]
    #[DataProvider('appUrlProvider')]
    public function get_globals_returns_expected_shape_and_preserves_value(string $appUrl): void
    {
        // Arrange
        $extension = new AppUrlTwigExtension($appUrl);

        // Act
        $globals = $extension->getGlobals();

        // Assert
        Assert::assertSame(['app_url' => $appUrl], $globals);
        Assert::assertArrayHasKey('app_url', $globals);
        Assert::assertCount(1, $globals);
    }

    public static function appUrlProvider(): \Iterator
    {
        yield 'https_url' => ['https://app.example.com'];
        yield 'http_with_port_and_path' => ['http://localhost:8080/base-path'];
        yield 'empty_string' => [''];
    }
}
