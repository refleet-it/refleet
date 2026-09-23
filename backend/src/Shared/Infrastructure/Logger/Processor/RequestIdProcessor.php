<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logger\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds request_id, trace_id and span_id to log records for correlation.
 * Uses OpenTelemetry context when available, falls back to headers or generated IDs.
 */
final class RequestIdProcessor implements ProcessorInterface
{
    private const string REQUEST_ID_HEADER = 'X-Request-ID';

    private const string TRACE_ID_HEADER = 'X-Trace-ID';

    private ?string $requestId = null;

    private ?string $traceId = null;

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    #[\Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        [$traceId, $spanId] = $this->getTraceContext();

        return $record->with(
            extra: \array_merge($record->extra, [
                'request_id' => $this->getRequestId(),
                'trace_id' => $traceId,
                'span_id' => $spanId,
            ])
        );
    }

    /**
     * @return array{string, string|null}
     */
    private function getTraceContext(): array
    {
        // Try OpenTelemetry if extension is loaded
        if (\extension_loaded('opentelemetry') && \class_exists(\OpenTelemetry\API\Trace\Span::class)) {
            $spanContext = \OpenTelemetry\API\Trace\Span::getCurrent()->getContext();
            $traceId = $spanContext->getTraceId();
            $spanId = $spanContext->getSpanId();

            if ('00000000000000000000000000000000' !== $traceId) {
                return [$traceId, '0000000000000000' !== $spanId ? $spanId : null];
            }
        }

        // Fallback to header or generate
        return [$this->getTraceIdFromHeaderOrGenerate(), null];
    }

    private function getTraceIdFromHeaderOrGenerate(): string
    {
        if (null !== $this->traceId) {
            return $this->traceId;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request && $request->headers->has(self::TRACE_ID_HEADER)) {
            $headerId = $request->headers->get(self::TRACE_ID_HEADER);
            $this->traceId = \is_string($headerId) ? $headerId : $this->generateTraceId();
        } else {
            $this->traceId = $this->generateTraceId();
        }

        return $this->traceId;
    }

    private function getRequestId(): string
    {
        if (null !== $this->requestId) {
            return $this->requestId;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request && $request->headers->has(self::REQUEST_ID_HEADER)) {
            $headerId = $request->headers->get(self::REQUEST_ID_HEADER);
            $this->requestId = \is_string($headerId) ? $headerId : $this->generateId();
        } else {
            $this->requestId = $this->generateId();
        }

        return $this->requestId;
    }

    private function generateId(): string
    {
        return \bin2hex(\random_bytes(8));
    }

    private function generateTraceId(): string
    {
        return \bin2hex(\random_bytes(16));
    }
}
