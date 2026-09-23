<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Metrics;

/**
 * Formats metrics into Prometheus exposition format.
 *
 * @see https://prometheus.io/docs/instrumenting/exposition_formats/
 */
final class PrometheusFormatter
{
    /**
     * @param list<Metric> $metrics
     */
    public function format(array $metrics): string
    {
        $grouped = $this->groupByName($metrics);
        $output = [];

        foreach ($grouped as $name => $metricsGroup) {
            $first = \reset($metricsGroup);
            if (false === $first) {
                continue;
            }

            $output[] = \sprintf('# HELP %s %s', $name, $this->escapeHelp($first->help));
            $output[] = \sprintf('# TYPE %s %s', $name, $first->type->value);

            foreach ($metricsGroup as $metric) {
                $output[] = $this->formatMetric($metric);
            }
        }

        return \implode("\n", $output)."\n";
    }

    private function formatMetric(Metric $metric): string
    {
        $labels = $this->formatLabels($metric->labels);

        return \sprintf(
            '%s%s %s',
            $metric->name,
            $labels,
            $this->formatValue($metric->value)
        );
    }

    /**
     * @param array<string, string> $labels
     */
    private function formatLabels(array $labels): string
    {
        if ([] === $labels) {
            return '';
        }

        $parts = [];
        foreach ($labels as $key => $value) {
            $parts[] = \sprintf('%s="%s"', $key, $this->escapeLabelValue($value));
        }

        return '{'.\implode(',', $parts).'}';
    }

    private function formatValue(float|int $value): string
    {
        if (\is_float($value)) {
            if (\is_nan($value)) {
                return 'NaN';
            }

            if (\is_infinite($value)) {
                return $value > 0 ? '+Inf' : '-Inf';
            }
        }

        return (string) $value;
    }

    private function escapeHelp(string $help): string
    {
        return \str_replace(['\\', "\n"], ['\\\\', '\\n'], $help);
    }

    private function escapeLabelValue(string $value): string
    {
        return \str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value);
    }

    /**
     * @param list<Metric> $metrics
     *
     * @return array<string, list<Metric>>
     */
    private function groupByName(array $metrics): array
    {
        $grouped = [];
        foreach ($metrics as $metric) {
            $grouped[$metric->name][] = $metric;
        }

        return $grouped;
    }
}
