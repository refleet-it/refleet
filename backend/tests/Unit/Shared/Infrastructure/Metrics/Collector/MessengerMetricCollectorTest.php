<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Metrics\Collector;

use App\Shared\Infrastructure\Metrics\Collector\MessengerMetricCollector;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessengerMetricCollector::class)]
final class MessengerMetricCollectorTest extends TestCase
{
    private Connection&MockObject $connection;

    #[Test]
    public function collects_metrics_only_for_configured_queues(): void
    {
        // Arrange
        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['queue_name' => 'to_notification', 'total' => '5', 'available' => '3', 'delayed' => '2'],
                ['queue_name' => 'failed', 'total' => '1', 'available' => '1', 'delayed' => '0'],
            ]);

        $collector = new MessengerMetricCollector($this->connection);

        // Act
        $metrics = $collector->collect();

        // Assert: 2 configured queues x 3 metrics (size/available/delayed) = 6, no dead
        // "to_payments"/"scheduler_default" queues that don't exist in messenger.yaml.
        Assert::assertCount(6, $metrics);

        $queuesSeen = [];
        $notificationSizeValue = null;
        foreach ($metrics as $metric) {
            $queuesSeen[$metric->labels['queue'] ?? ''] = true;
            if ('messenger_queue_size' === $metric->name && 'to_notification' === ($metric->labels['queue'] ?? null)) {
                $notificationSizeValue = $metric->value;
            }
        }

        $queueNames = \array_keys($queuesSeen);
        \sort($queueNames);
        Assert::assertSame(['failed', 'to_notification'], $queueNames);
        Assert::assertSame(5, $notificationSizeValue);
    }

    #[Test]
    public function returns_zeroed_metrics_when_query_fails(): void
    {
        // Arrange
        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willThrowException(new \RuntimeException('table missing'));

        $collector = new MessengerMetricCollector($this->connection);

        // Act
        $metrics = $collector->collect();

        // Assert
        Assert::assertCount(6, $metrics);
        foreach ($metrics as $metric) {
            Assert::assertSame(0, $metric->value);
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
    }
}
