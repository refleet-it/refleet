<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Listener;

use App\Shared\Infrastructure\Listener\EnsureJsonRequestListener;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(EnsureJsonRequestListener::class)]
final class EnsureJsonRequestListenerTest extends TestCase
{
    #[Test]
    public function sets_default_content_type_when_missing(): void
    {
        // Arrange
        $request = Request::create('/any', 'GET');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new EnsureJsonRequestListener();

        // Pre-assert: header not set
        Assert::assertFalse($request->headers->has('Content-Type'));

        // Act
        $listener($event);

        // Assert
        Assert::assertTrue($request->headers->has('Content-Type'));
        Assert::assertSame('application/json', $request->headers->get('Content-Type'));
    }

    #[Test]
    public function does_not_override_existing_content_type(): void
    {
        // Arrange
        $request = Request::create('/any', 'POST', server: ['CONTENT_TYPE' => 'application/xml']);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new EnsureJsonRequestListener();

        // Pre-assert: header already set
        Assert::assertTrue($request->headers->has('Content-Type'));
        Assert::assertSame('application/xml', $request->headers->get('Content-Type'));

        // Act
        $listener($event);

        // Assert: unchanged
        Assert::assertSame('application/xml', $request->headers->get('Content-Type'));
    }
}
