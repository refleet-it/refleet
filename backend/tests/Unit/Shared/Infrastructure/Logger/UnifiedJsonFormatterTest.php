<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Logger;

use App\Shared\Infrastructure\Logger\UnifiedJsonFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnifiedJsonFormatter::class)]
final class UnifiedJsonFormatterTest extends TestCase
{
    #[Test]
    public function format_maps_known_fields_and_keeps_only_unmapped_context_and_extra(): void
    {
        // Arrange
        $formatter = new UnifiedJsonFormatter();
        $record = new LogRecord(
            datetime: new \DateTimeImmutable('2026-01-20T15:16:17+00:00'),
            channel: 'payments',
            level: Level::Warning,
            message: 'checkout failed',
            context: [
                'user_id' => 'u-123',
                'email' => 'dev@example.com',
                'order_id' => 'ord-1',
            ],
            extra: [
                'request_id' => 'req-42',
                'ip' => '127.0.0.1',
                'http_method' => 'POST',
                'url' => '/api/checkout',
                'user_agent' => 'phpunit',
                'trace_id' => 'trace-1',
                'remaining' => 'value',
            ],
        );

        // Act
        $formatted = $formatter->format($record);
        $decoded = \json_decode($formatted, true, 512, \JSON_THROW_ON_ERROR);

        // Assert
        Assert::assertStringEndsWith("\n", $formatted);
        Assert::assertSame('checkout failed', $decoded['msg']);
        Assert::assertSame('WARNING', $decoded['level']);
        Assert::assertSame('2026-01-20T15:16:17+00:00', $decoded['time']);
        Assert::assertSame('2026-01-20T15:16:17+00:00', $decoded['ts']);
        Assert::assertSame('payments', $decoded['channel']);
        Assert::assertSame('payments', $decoded['logger']);
        Assert::assertSame('u-123', $decoded['user_id']);
        Assert::assertSame('dev@example.com', $decoded['email']);
        Assert::assertSame('req-42', $decoded['request_id']);
        Assert::assertSame('127.0.0.1', $decoded['ip']);
        Assert::assertSame('POST', $decoded['method']);
        Assert::assertSame('/api/checkout', $decoded['url']);
        Assert::assertSame('phpunit', $decoded['user_agent']);
        Assert::assertSame('trace-1', $decoded['trace_id']);
        Assert::assertSame(['order_id' => 'ord-1'], $decoded['context']);
        Assert::assertSame(['remaining' => 'value'], $decoded['extra']);
        Assert::assertArrayNotHasKey('message', $decoded);
    }

    #[Test]
    public function format_omits_context_and_extra_keys_when_everything_is_consumed_by_mapping(): void
    {
        // Arrange
        $formatter = new UnifiedJsonFormatter();
        $record = new LogRecord(
            datetime: new \DateTimeImmutable('2026-01-21T01:02:03+00:00'),
            channel: 'app',
            level: Level::Info,
            message: 'hello',
            context: [
                'user_id' => 'u-1',
                'email' => 'u@example.com',
            ],
            extra: [
                'request_id' => 'req-1',
                'ip' => '10.0.0.1',
                'http_method' => 'GET',
                'url' => '/healthz',
                'user_agent' => 'agent',
                'trace_id' => 'trace',
            ],
        );

        // Act
        $decoded = \json_decode($formatter->format($record), true, 512, \JSON_THROW_ON_ERROR);

        // Assert
        Assert::assertArrayNotHasKey('context', $decoded);
        Assert::assertArrayNotHasKey('extra', $decoded);
    }

    #[Test]
    public function format_keeps_unmapped_context_and_extra_without_modification(): void
    {
        // Arrange
        $formatter = new UnifiedJsonFormatter();
        $record = new LogRecord(
            datetime: new \DateTimeImmutable('2026-01-22T11:22:33+00:00'),
            channel: 'audit',
            level: Level::Error,
            message: 'error',
            context: [
                'tenant' => 'acme',
                'code' => 500,
            ],
            extra: [
                'node' => 'worker-1',
            ],
        );

        // Act
        $decoded = \json_decode($formatter->format($record), true, 512, \JSON_THROW_ON_ERROR);

        // Assert
        Assert::assertSame(['tenant' => 'acme', 'code' => 500], $decoded['context']);
        Assert::assertSame(['node' => 'worker-1'], $decoded['extra']);
        Assert::assertSame('ERROR', $decoded['level']);
    }
}
