<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http\ClientError;

use App\Shared\Domain\Exception\TooManyRequestsException;
use App\Shared\Infrastructure\Http\ClientError\ClientErrorController;
use App\Shared\Infrastructure\Http\ClientError\ClientErrorRequest;
use App\Shared\Infrastructure\Security\RateLimiter;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ClientErrorController::class)]
final class ClientErrorControllerTest extends TestCase
{
    private LoggerInterface&MockObject $logger;

    private RateLimiter $rateLimiter;

    #[Test]
    public function logs_client_error_and_returns_no_content(): void
    {
        // Arrange
        $payload = new ClientErrorRequest(
            message: 'TypeError: Cannot read properties of undefined',
            url: 'https://refleet.it/workspace/42',
            stack: "at foo (main.js:1:1)\nat bar (main.js:2:2)",
            componentStack: 'AppComponent > DashboardComponent',
        );
        $request = Request::create('/', server: ['REMOTE_ADDR' => '198.51.100.1']);
        $request->headers->set('User-Agent', 'Mozilla/5.0 Test');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                $payload->message,
                [
                    'url' => $payload->url,
                    'stack' => $payload->stack,
                    'component_stack' => $payload->componentStack,
                    'user_agent' => 'Mozilla/5.0 Test',
                ],
            );

        $controller = new ClientErrorController($this->rateLimiter, $this->logger);

        // Act
        $response = $controller(payload: $payload, request: $request);

        // Assert
        Assert::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    #[Test]
    public function throttles_after_max_attempts_from_same_ip(): void
    {
        // Arrange
        $payload = new ClientErrorRequest(message: 'boom', url: 'https://refleet.it/');
        $request = Request::create('/', server: ['REMOTE_ADDR' => '198.51.100.2']);

        $this->logger->expects($this->exactly(20))->method('error');

        $controller = new ClientErrorController($this->rateLimiter, $this->logger);

        // Act: exhaust the allowed attempts for this IP
        for ($i = 0; $i < 20; ++$i) {
            $controller(payload: $payload, request: $request);
        }

        // Assert
        $this->expectException(TooManyRequestsException::class);
        $controller(payload: $payload, request: $request);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->rateLimiter = new RateLimiter(new ArrayAdapter());
    }
}
