<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Listener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Detects when a POST request exceeds PHP's post_max_size limit.
 *
 * When post_max_size is exceeded, PHP silently discards $_POST and $_FILES data
 * and may output an HTML warning (if display_errors=On), corrupting the response.
 * This listener intercepts the request early and returns 413 with a clean JSON response.
 */
#[AsEventListener(event: 'kernel.request', priority: 2050)]
final readonly class PostMaxSizeExceededListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ('POST' !== $request->getMethod()) {
            return;
        }

        $rawContentLength = $request->server->get('CONTENT_LENGTH', '0');
        $contentLength = \is_string($rawContentLength) ? (int) $rawContentLength : 0;
        if ($contentLength <= 0) {
            return;
        }

        $postMaxSize = $this->getPostMaxSizeBytes();
        if ($postMaxSize <= 0) {
            return;
        }

        if ($contentLength > $postMaxSize) {
            $event->setResponse(new JsonResponse(
                [
                    'error' => 'REQUEST_TOO_LARGE',
                    'message' => \sprintf(
                        'Request size (%s) exceeds the maximum allowed size (%s).',
                        $this->formatBytes($contentLength),
                        $this->formatBytes($postMaxSize),
                    ),
                ],
                Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
            ));
        }
    }

    private function getPostMaxSizeBytes(): int
    {
        $postMaxSize = \ini_get('post_max_size');

        return false === $postMaxSize ? 0 : $this->parseIniSize($postMaxSize);
    }

    private function parseIniSize(string $size): int
    {
        $size = \trim($size);
        if ('' === $size || '0' === $size) {
            return 0;
        }

        $unit = \strtoupper($size[\strlen($size) - 1]);
        $value = (int) $size;

        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return \round($bytes / (1024 * 1024 * 1024), 2).'GB';
        }

        if ($bytes >= 1024 * 1024) {
            return \round($bytes / (1024 * 1024), 2).'MB';
        }

        if ($bytes >= 1024) {
            return \round($bytes / 1024, 2).'KB';
        }

        return $bytes.'B';
    }
}
