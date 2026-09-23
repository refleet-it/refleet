<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Listener;

use App\Shared\Domain\Exception\AppException;
use App\Shared\Infrastructure\Listener\UnwrapAppExceptionListener;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(UnwrapAppExceptionListener::class)]
final class UnwrapAppExceptionListenerTest extends TestCase
{
    #[Test]
    public function unwraps_app_exception_from_handler_failed_exception(): void
    {
        // Arrange
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/any', 'POST');

        $message = new \stdClass();
        $envelope = new Envelope($message);
        $appException = new AppException('Boom', 123);
        $handlerFailed = new HandlerFailedException($envelope, [$appException]);

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $handlerFailed);

        $listener = new UnwrapAppExceptionListener();

        // Pre-assert: event holds HandlerFailedException
        Assert::assertSame($handlerFailed, $event->getThrowable());

        // Act
        $listener($event);

        // Assert: throwable was unwrapped to AppException
        Assert::assertSame($appException, $event->getThrowable());
    }

    #[Test]
    public function does_not_unwrap_when_previous_is_not_app_exception(): void
    {
        // Arrange
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/any', 'POST');

        $message = new \stdClass();
        $envelope = new Envelope($message);
        $previous = new \RuntimeException('No unwrap');
        $handlerFailed = new HandlerFailedException($envelope, [$previous]);

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $handlerFailed);

        $listener = new UnwrapAppExceptionListener();

        // Act
        $listener($event);

        // Assert: throwable remains the original HandlerFailedException
        Assert::assertSame($handlerFailed, $event->getThrowable());
    }

    #[Test]
    public function ignores_non_handler_failed_exceptions(): void
    {
        // Arrange
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/any', 'POST');
        $original = new \RuntimeException('Any');
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $original);

        $listener = new UnwrapAppExceptionListener();

        // Act
        $listener($event);

        // Assert: throwable is unchanged
        Assert::assertSame($original, $event->getThrowable());
    }
}
