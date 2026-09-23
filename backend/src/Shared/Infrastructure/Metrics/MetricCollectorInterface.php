<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Metrics;

/**
 * Interface for collecting Prometheus metrics from bounded contexts.
 * Each context implements this interface to expose its metrics.
 */
interface MetricCollectorInterface
{
    /**
     * @return list<Metric>
     */
    public function collect(): array;
}
