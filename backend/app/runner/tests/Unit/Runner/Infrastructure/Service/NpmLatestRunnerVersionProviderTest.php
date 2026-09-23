<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Infrastructure\Service;

use App\Runner\Runner\Infrastructure\Service\NpmLatestRunnerVersionProvider;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(NpmLatestRunnerVersionProvider::class)]
final class NpmLatestRunnerVersionProviderTest extends TestCase
{
    private const string URL = 'https://registry.npmjs.org/@refleet-it/runner/latest';

    #[Test]
    public function reads_the_version_of_the_latest_dist_tag_and_caches_it(): void
    {
        // Arrange
        $httpClient = new MockHttpClient([new MockResponse(\json_encode(['name' => '@refleet-it/runner', 'version' => '0.1.200']), ['http_code' => 200])]);
        $provider = new NpmLatestRunnerVersionProvider($httpClient, new ArrayAdapter(), self::URL);

        // Act
        $first = $provider->latestVersion();
        $second = $provider->latestVersion();

        // Assert
        Assert::assertSame('0.1.200', $first);
        Assert::assertSame('0.1.200', $second);
        Assert::assertSame(1, $httpClient->getRequestsCount());
    }

    #[Test]
    public function answers_null_when_the_registry_fails_or_answers_nonsense(): void
    {
        // Arrange
        $failing = new NpmLatestRunnerVersionProvider(new MockHttpClient([new MockResponse('', ['http_code' => 503])]), new ArrayAdapter(), self::URL);
        $nonsense = new NpmLatestRunnerVersionProvider(new MockHttpClient([new MockResponse('not json', ['http_code' => 200])]), new ArrayAdapter(), self::URL);
        $unreachable = new NpmLatestRunnerVersionProvider(new MockHttpClient([new MockResponse('', ['error' => 'connection refused'])]), new ArrayAdapter(), self::URL);

        // Act & Assert
        Assert::assertNull($failing->latestVersion());
        Assert::assertNull($nonsense->latestVersion());
        Assert::assertNull($unreachable->latestVersion());
    }

    #[Test]
    public function an_empty_url_switches_the_lookup_off(): void
    {
        // Arrange
        $httpClient = new MockHttpClient();
        $provider = new NpmLatestRunnerVersionProvider($httpClient, new ArrayAdapter(), '');

        // Act & Assert
        Assert::assertNull($provider->latestVersion());
        Assert::assertSame(0, $httpClient->getRequestsCount());
    }
}
