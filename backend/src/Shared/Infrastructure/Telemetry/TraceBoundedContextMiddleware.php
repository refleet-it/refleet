<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Telemetry;

use OpenTelemetry\API\Trace\Span;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class TraceBoundedContextMiddleware implements MiddlewareInterface
{
    #[\Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $context = BoundedContext::fromClassName($envelope->getMessage()::class);

        if (null !== $context) {
            Span::getCurrent()->setAttribute(BoundedContext::SPAN_ATTRIBUTE, $context);
        }

        $envelope = $stack->next()->handle($envelope, $stack);

        $handled = $envelope->last(HandledStamp::class);

        if (null !== $handled) {
            $handlerContext = BoundedContext::fromClassName($handled->getHandlerName());

            if (null !== $handlerContext) {
                Span::getCurrent()->setAttribute(BoundedContext::SPAN_ATTRIBUTE, $handlerContext);
            }
        }

        return $envelope;
    }
}
