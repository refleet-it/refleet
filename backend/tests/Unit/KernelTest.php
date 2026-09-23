<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Kernel::class)]
final class KernelTest extends TestCase
{
    #[Test]
    public function resolves_project_cache_and_log_directories_from_kernel_location(): void
    {
        // Arrange
        $kernel = new Kernel('test', false);
        $expectedProjectDir = \realpath(__DIR__.'/../../');

        // Act
        $projectDir = \realpath($kernel->getProjectDir());
        $cacheDir = $kernel->getCacheDir();
        $logDir = $kernel->getLogDir();

        // Assert
        Assert::assertNotFalse($expectedProjectDir);
        Assert::assertNotFalse($projectDir);
        Assert::assertSame($expectedProjectDir, $projectDir);
        Assert::assertStringEndsWith('/var/cache/monolith/test', $cacheDir);
        Assert::assertStringEndsWith('/var/log/monolith', $logDir);
    }

    #[Test]
    public function separates_cache_and_config_directories_per_application(): void
    {
        // Arrange
        $kernel = new Kernel('test', false, 'identity');

        // Act & Assert
        Assert::assertSame('identity', $kernel->getAppId());
        Assert::assertStringEndsWith('/var/cache/identity/test', $kernel->getCacheDir());
        Assert::assertStringEndsWith('/var/log/identity', $kernel->getLogDir());
        Assert::assertStringEndsWith('/app/identity/config', $kernel->getAppConfigDir());
    }

    #[Test]
    public function registers_bundles_according_to_test_environment(): void
    {
        // Arrange
        $kernel = new Kernel('test', false);

        // Act
        $kernel->boot();

        $bundles = \array_keys($kernel->getBundles());
        $kernel->shutdown();

        // Assert
        Assert::assertContains('FrameworkBundle', $bundles);
        Assert::assertContains('WebProfilerBundle', $bundles);
        Assert::assertContains('DoctrineFixturesBundle', $bundles);
        Assert::assertContains('ZenstruckFoundryBundle', $bundles);
        Assert::assertNotContains('DebugBundle', $bundles);
        Assert::assertNotContains('MakerBundle', $bundles);
    }
}
