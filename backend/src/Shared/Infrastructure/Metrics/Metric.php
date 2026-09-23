<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Metrics;

/**
 * Represents a single Prometheus metric.
 */
final readonly class Metric
{
    /**
     * @param array<string, string> $labels
     */
    public function __construct(
        public string $name,
        public float|int $value,
        public MetricType $type,
        public string $help,
        public array $labels = [],
    ) {
    }

    /**
     * @param array<string, string> $labels
     */
    public static function gauge(string $name, float|int $value, string $help, array $labels = []): self
    {
        return new self($name, $value, MetricType::GAUGE, $help, $labels);
    }

    /**
     * @param array<string, string> $labels
     */
    public static function counter(string $name, float|int $value, string $help, array $labels = []): self
    {
        return new self($name, $value, MetricType::COUNTER, $help, $labels);
    }
}
