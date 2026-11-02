<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Telemetry;

use OpenTelemetry\API\Trace\Span;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

#[AsEventListener(event: 'kernel.controller')]
final readonly class TraceBoundedContextListener
{
    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $controller = $event->getController();

        if (\is_array($controller)) {
            // A callable array is always [object|class, method], so index 0 is guaranteed —
            // phpstan narrows getController() to non-empty-list&callable and rejects a
            // null-coalesce here as dead code.
            $controller = $controller[0];
        }

        $class = match (true) {
            \is_object($controller) && !$controller instanceof \Closure => $controller::class,
            \is_string($controller) => $controller,
            default => null,
        };

        if (null === $class) {
            return;
        }

        $context = BoundedContext::fromClassName($class);

        if (null === $context) {
            return;
        }

        Span::getCurrent()->setAttribute(BoundedContext::SPAN_ATTRIBUTE, $context);
    }
}
