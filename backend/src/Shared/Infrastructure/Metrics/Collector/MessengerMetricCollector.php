<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Metrics\Collector;

use App\Shared\Infrastructure\Metrics\Metric;
use App\Shared\Infrastructure\Metrics\MetricCollectorInterface;
use Doctrine\DBAL\Connection;

/**
 * Collects Symfony Messenger queue metrics from Doctrine transport.
 */
final readonly class MessengerMetricCollector implements MetricCollectorInterface
{
    // Keep in sync with the transports actually configured in config/packages/messenger.yaml.
    private const array QUEUES = ['to_notification', 'failed'];

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[\Override]
    public function collect(): array
    {
        $metrics = [];
        $stats = $this->fetchQueueStats();

        foreach (self::QUEUES as $queue) {
            $queueStats = $stats[$queue] ?? ['total' => 0, 'available' => 0, 'delayed' => 0];

            $metrics[] = Metric::gauge(
                'messenger_queue_size',
                $queueStats['total'],
                'Total number of messages in the queue',
                ['queue' => $queue]
            );

            $metrics[] = Metric::gauge(
                'messenger_queue_available',
                $queueStats['available'],
                'Number of messages available for processing',
                ['queue' => $queue]
            );

            $metrics[] = Metric::gauge(
                'messenger_queue_delayed',
                $queueStats['delayed'],
                'Number of delayed messages (scheduled for later)',
                ['queue' => $queue]
            );
        }

        return $metrics;
    }

    /**
     * @return array<string, array{total: int, available: int, delayed: int}>
     */
    private function fetchQueueStats(): array
    {
        $sql = <<<'SQL'
            SELECT
                queue_name,
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE available_at <= NOW() AND delivered_at IS NULL) as available,
                COUNT(*) FILTER (WHERE available_at > NOW()) as delayed
            FROM messenger_messages
            GROUP BY queue_name
            SQL;

        try {
            $results = $this->connection->fetchAllAssociative($sql);
        } catch (\Throwable) {
            return [];
        }

        $stats = [];
        foreach ($results as $row) {
            $stats[$this->extractStringField($row, 'queue_name')] = [
                'total' => $this->extractIntField($row, 'total'),
                'available' => $this->extractIntField($row, 'available'),
                'delayed' => $this->extractIntField($row, 'delayed'),
            ];
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function extractStringField(array $row, string $key): string
    {
        return isset($row[$key]) && \is_string($row[$key]) ? $row[$key] : '';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function extractIntField(array $row, string $key): int
    {
        return isset($row[$key]) && \is_numeric($row[$key]) ? (int) $row[$key] : 0;
    }
}
