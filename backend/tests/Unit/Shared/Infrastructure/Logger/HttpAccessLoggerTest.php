<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Logger;

use App\Shared\Infrastructure\Logger\HttpAccessLogger;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(HttpAccessLogger::class)]
final class HttpAccessLoggerTest extends TestCase
{
    #[Test]
    public function on_response_logs_main_request_with_sanitized_context(): void
    {
        // Arrange
        $request = Request::create('/orders?password=secret-value&token=abc&search=desk', 'POST');
        $request->attributes->set('_route', 'orders_create');
        $request->server->set('REQUEST_TIME_FLOAT', \microtime(true) - 0.02);

        $response = new Response(status: Response::HTTP_CREATED);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'http_access',
                self::callback(static function (array $context): bool {
                    Assert::assertSame('POST', $context['method'] ?? null);
                    Assert::assertSame('/orders', $context['path'] ?? null);
                    Assert::assertSame('orders_create', $context['route'] ?? null);
                    Assert::assertSame(Response::HTTP_CREATED, $context['status'] ?? null);
                    Assert::assertIsInt($context['duration_ms'] ?? null);
                    Assert::assertGreaterThanOrEqual(0, $context['duration_ms']);
                    Assert::assertSame([
                        'password' => '***',
                        'token' => '***',
                        'search' => 'desk',
                    ], $context['query'] ?? null);

                    return true;
                }),
            );

        $loggerService = new HttpAccessLogger($logger);

        // Act
        $loggerService->onResponse($event);

        // Assert
        Assert::assertTrue($request->attributes->getBoolean('_access_logged'));
    }

    #[Test]
    public function on_response_skips_sub_request(): void
    {
        // Arrange
        $request = Request::create('/orders', 'GET');
        $response = new Response(status: Response::HTTP_OK);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $loggerService = new HttpAccessLogger($logger);

        // Act
        $loggerService->onResponse($event);

        // Assert
        Assert::assertFalse($request->attributes->has('_access_logged'));
    }

    #[Test]
    public function on_terminate_does_not_log_when_already_logged(): void
    {
        // Arrange
        $request = Request::create('/orders', 'GET');
        $request->attributes->set('_access_logged', true);

        $response = new Response(status: Response::HTTP_OK);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new TerminateEvent($kernel, $request, $response);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $loggerService = new HttpAccessLogger($logger);

        // Act
        $loggerService->onTerminate($event);

        // Assert
        Assert::assertTrue($request->attributes->getBoolean('_access_logged'));
    }

    #[Test]
    public function on_response_marks_request_as_logged_but_skips_health_paths(): void
    {
        // Arrange
        $request = Request::create('/healthz', 'GET');
        $response = new Response(status: Response::HTTP_OK);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $loggerService = new HttpAccessLogger($logger);

        // Act
        $loggerService->onResponse($event);

        // Assert
        Assert::assertTrue($request->attributes->getBoolean('_access_logged'));
    }

    #[Test]
    public function on_response_sets_duration_to_null_when_request_start_is_not_float(): void
    {
        // Arrange
        $request = Request::create('/orders', 'GET');
        $request->server->set('REQUEST_TIME_FLOAT', 'not-a-float');

        $response = new Response(status: Response::HTTP_OK);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'http_access',
                self::callback(static function (array $context): bool {
                    Assert::assertArrayHasKey('duration_ms', $context);
                    Assert::assertNull($context['duration_ms']);

                    return true;
                }),
            );
        $loggerService = new HttpAccessLogger($logger);

        // Act
        $loggerService->onResponse($event);

        // Assert
        Assert::assertTrue($request->attributes->getBoolean('_access_logged'));
    }
}
