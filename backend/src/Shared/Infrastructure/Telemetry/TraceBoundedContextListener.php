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
            $controller = $controller[0] ?? null;
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
