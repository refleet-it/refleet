<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logger;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class HttpAccessLogger
{
    private const string ACCESS_LOGGED_ATTR = '_access_logged';

    public function __construct(
        #[Autowire(service: 'monolog.logger')]
        private LoggerInterface $logger,
    ) {
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function onTerminate(TerminateEvent $event): void
    {
        $this->logOnce($event->getRequest(), $event->getResponse()->getStatusCode());
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->logOnce($event->getRequest(), $event->getResponse()->getStatusCode());
    }

    private function logOnce(Request $request, ?int $status): void
    {
        if (true === $request->attributes->get(self::ACCESS_LOGGED_ATTR, false)) {
            return;
        }

        $request->attributes->set(self::ACCESS_LOGGED_ATTR, true);

        // Avoid logging health checks/metrics if present
        $path = $request->getPathInfo();
        if (\in_array($path, ['/healthz', '/readyz', '/metrics'], true)) {
            return;
        }

        $this->logger->info('http_access', [
            'method' => $request->getMethod(),
            'path' => $path,
            'route' => $request->attributes->get('_route'),
            'status' => $status,
            'duration_ms' => $this->durationMs($request),
            'query' => $this->sanitize($request->query->all()),
        ]);
    }

    private function durationMs(Request $request): ?int
    {
        $start = $request->server->get('REQUEST_TIME_FLOAT');
        if (!\is_float($start)) {
            return null;
        }

        return (int) \round((\microtime(true) - $start) * 1000);
    }

    /**
     * Shallow sanitize: remove common secret keys from query arrays.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        $secrets = ['password', 'token', 'authorization', 'auth', 'secret'];
        foreach ($secrets as $key) {
            if (\array_key_exists($key, $data)) {
                $data[$key] = '***';
            }
        }

        return $data;
    }
}
