<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE, priority: 1000)]
final readonly class SecurityHeadersListener
{
    private const string PATH_PREFIX_PROFILER = '/_profiler';

    private const string PATH_PREFIX_WDT = '/_wdt';

    private const string PATH_API_DOC = '/api/doc';

    private const string PATH_API_DOC_JSON = '/api/doc.json';

    private const string PATH_PREFIX_API = '/api/';

    private const string PATH_API_HEALTH = '/api/health';

    private const string CSP_API = "default-src 'none'; frame-ancestors 'none'";

    private const string CACHE_CONTROL_NO_STORE = 'no-cache, no-store, must-revalidate';

    private const string CACHE_CONTROL_ETAG = 'no-cache, private';

    private const string HSTS_VALUE = 'max-age=31536000; includeSubDomains';

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $headers = $response->headers;
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-XSS-Protection', '1; mode=block');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Only meaningful (and only sent by browsers) over an actual HTTPS
        // connection - never on plain HTTP local dev.
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', self::HSTS_VALUE);
        }

        // Apply a very strict CSP only to actual API endpoints to avoid
        // breaking developer tooling (e.g. Symfony Profiler) or documentation UI
        // (e.g. NelmioApiDoc Swagger UI), which legitimately requires scripts/styles.
        $this->applyContentSecurityPolicy($headers, $path);

        $headers->remove('X-Powered-By');
        $headers->remove('Server');

        $this->applyCacheControl($response, $path);
    }

    private function applyContentSecurityPolicy(ResponseHeaderBag $headers, string $path): void
    {
        $isProfiler = \str_starts_with($path, self::PATH_PREFIX_PROFILER) || \str_starts_with($path, self::PATH_PREFIX_WDT);
        $isApiDoc = self::PATH_API_DOC === $path || self::PATH_API_DOC_JSON === $path;
        $isApiEndpoint = \str_starts_with($path, self::PATH_PREFIX_API) && !$isApiDoc;

        if ($isApiEndpoint && !$isProfiler) {
            $headers->set('Content-Security-Policy', self::CSP_API);
        }
    }

    private function applyCacheControl(Response $response, string $path): void
    {
        $isExcluded = \in_array($path, [self::PATH_API_HEALTH, self::PATH_API_DOC, self::PATH_API_DOC_JSON], true);

        if (!\str_starts_with($path, self::PATH_PREFIX_API) || $isExcluded) {
            return;
        }

        if ($response->headers->has('ETag')) {
            $response->headers->set('Cache-Control', self::CACHE_CONTROL_ETAG);
        } else {
            $response->headers->set('Cache-Control', self::CACHE_CONTROL_NO_STORE);
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
    }
}
