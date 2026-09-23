<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Refuses to serve production traffic if CORS_ALLOW_ORIGIN is empty or a
 * match-all pattern - nelmio_cors.yaml compiles it as a regex
 * (origin_regex: true), so a wildcard there would let any site read
 * authenticated responses via credentialed CORS requests.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 2000)]
final readonly class CorsConfigurationGuard
{
    private const array INSECURE_PATTERNS = ['*', '.*', '.+', '^.*$', '^.+$'];

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $environment,
        #[Autowire('%env(CORS_ALLOW_ORIGIN)%')]
        private string $corsAllowOrigin,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || 'prod' !== $this->environment) {
            return;
        }

        if ('' === $this->corsAllowOrigin || \in_array($this->corsAllowOrigin, self::INSECURE_PATTERNS, true)) {
            throw new \RuntimeException('CORS_ALLOW_ORIGIN must be a specific origin pattern in production, not empty or a match-all wildcard.');
        }
    }
}
