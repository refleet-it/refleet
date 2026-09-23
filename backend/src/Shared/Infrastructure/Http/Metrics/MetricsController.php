<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Metrics;

use App\Shared\Infrastructure\Metrics\MetricCollectorInterface;
use App\Shared\Infrastructure\Metrics\PrometheusFormatter;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exposes Prometheus metrics collected from all bounded contexts.
 * This endpoint is protected by Caddy to only allow internal access.
 */
#[Route('/metrics', name: 'metrics', methods: ['GET'])]
final readonly class MetricsController
{
    /**
     * @param iterable<MetricCollectorInterface> $collectors
     */
    public function __construct(
        #[AutowireIterator('app.metric_collector')]
        private iterable $collectors,
        private PrometheusFormatter $formatter,
    ) {
    }

    public function __invoke(): Response
    {
        $metrics = [];

        foreach ($this->collectors as $collector) {
            $metrics = \array_merge($metrics, $collector->collect());
        }

        return new Response(
            $this->formatter->format($metrics),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8']
        );
    }
}
