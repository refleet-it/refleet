<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Listener;

use App\Shared\Infrastructure\Listener\PostMaxSizeExceededListener;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(PostMaxSizeExceededListener::class)]
final class PostMaxSizeExceededListenerTest extends TestCase
{
    #[Test]
    public function does_nothing_for_get_request(): void
    {
        $request = Request::create('/api/files/upload', 'GET', server: ['CONTENT_LENGTH' => '999999999']);
        $event = $this->createEvent($request);
        $listener = new PostMaxSizeExceededListener();

        $listener($event);

        Assert::assertNull($event->getResponse());
    }

    #[Test]
    public function does_nothing_when_content_length_is_zero(): void
    {
        $request = Request::create('/api/files/upload', 'POST', server: ['CONTENT_LENGTH' => '0']);
        $event = $this->createEvent($request);
        $listener = new PostMaxSizeExceededListener();

        $listener($event);

        Assert::assertNull($event->getResponse());
    }

    #[Test]
    public function does_nothing_when_content_length_is_within_limit(): void
    {
        $postMaxSize = $this->getPostMaxSizeBytes();
        if ($postMaxSize <= 0) {
            $this->markTestSkipped('post_max_size is unlimited or unavailable');
        }

        $request = Request::create('/api/files/upload', 'POST', server: ['CONTENT_LENGTH' => (string) ($postMaxSize - 1)]);
        $event = $this->createEvent($request);
        $listener = new PostMaxSizeExceededListener();

        $listener($event);

        Assert::assertNull($event->getResponse());
    }

    #[Test]
    public function returns_413_when_content_length_exceeds_post_max_size(): void
    {
        $postMaxSize = $this->getPostMaxSizeBytes();
        if ($postMaxSize <= 0) {
            $this->markTestSkipped('post_max_size is unlimited or unavailable');
        }

        $oversized = $postMaxSize + 1;
        $request = Request::create('/api/files/upload', 'POST', server: ['CONTENT_LENGTH' => (string) $oversized]);
        $event = $this->createEvent($request);
        $listener = new PostMaxSizeExceededListener();

        $listener($event);

        $response = $event->getResponse();
        Assert::assertNotNull($response);
        Assert::assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $response->getStatusCode());

        $data = \json_decode((string) $response->getContent(), true);
        Assert::assertIsArray($data);
        Assert::assertSame('REQUEST_TOO_LARGE', $data['error']);
        Assert::assertStringContainsString('exceeds', (string) $data['message']);
    }

    private function createEvent(Request $request): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function getPostMaxSizeBytes(): int
    {
        $postMaxSize = \ini_get('post_max_size');
        if (false === $postMaxSize || '' === $postMaxSize || '0' === $postMaxSize) {
            return 0;
        }

        $size = \trim($postMaxSize);
        $unit = \strtoupper($size[\strlen($size) - 1]);
        $value = (int) $size;

        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
    }
}
