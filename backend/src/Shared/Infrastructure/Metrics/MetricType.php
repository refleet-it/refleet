<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Metrics;

enum MetricType: string
{
    case COUNTER = 'counter';
    case GAUGE = 'gauge';
    case HISTOGRAM = 'histogram';
    case SUMMARY = 'summary';
}
