<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Security;

use App\Shared\Infrastructure\Security\SecurityHeadersListener;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(SecurityHeadersListener::class)]
final class SecurityHeadersListenerTest extends TestCase
{
    #[Test]
    public function skips_all_changes_for_sub_requests(): void
    {
        // Arrange
        $listener = new SecurityHeadersListener();
        $event = $this->createEvent('/api/orders', HttpKernelInterface::SUB_REQUEST);
        $response = $event->getResponse();
        $initialCacheControl = $response->headers->get('Cache-Control');
        $response->headers->set('Server', 'nginx');
        $response->headers->set('X-Powered-By', 'php');

        // Act
        $listener($event);

        // Assert
        Assert::assertSame('nginx', $response->headers->get('Server'));
        Assert::assertSame('php', $response->headers->get('X-Powered-By'));
        Assert::assertFalse($response->headers->has('X-Content-Type-Options'));
        Assert::assertFalse($response->headers->has('Content-Security-Policy'));
        Assert::assertSame($initialCacheControl, $response->headers->get('Cache-Control'));
        Assert::assertFalse($response->headers->hasCacheControlDirective('no-store'));
    }

    #[Test]
    public function adds_security_csp_and_no_cache_headers_on_regular_api_endpoint(): void
    {
        // Arrange
        $listener = new SecurityHeadersListener();
        $event = $this->createEvent('/api/orders');
        $response = $event->getResponse();
        $response->headers->set('Server', 'nginx');
        $response->headers->set('X-Powered-By', 'php');

        // Act
        $listener($event);

        // Assert
        Assert::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        Assert::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        Assert::assertSame('1; mode=block', $response->headers->get('X-XSS-Protection'));
        Assert::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        Assert::assertSame('camera=(), microphone=(), geolocation=(), payment=()', $response->headers->get('Permissions-Policy'));
        Assert::assertSame("default-src 'none'; frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        Assert::assertTrue($response->headers->hasCacheControlDirective('no-cache'));
        Assert::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        Assert::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        Assert::assertSame('no-cache', $response->headers->get('Pragma'));
        Assert::assertSame('0', $response->headers->get('Expires'));
        Assert::assertFalse($response->headers->has('Server'));
        Assert::assertFalse($response->headers->has('X-Powered-By'));
        Assert::assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    #[Test]
    public function adds_hsts_header_when_request_is_secure(): void
    {
        // Arrange
        $listener = new SecurityHeadersListener();
        $event = $this->createEvent('/api/orders', HttpKernelInterface::MAIN_REQUEST, secure: true);
        $response = $event->getResponse();

        // Act
        $listener($event);

        // Assert
        Assert::assertSame('max-age=31536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function does_not_add_hsts_header_over_plain_http(): void
    {
        // Arrange
        $listener = new SecurityHeadersListener();
        $event = $this->createEvent('/api/orders');
        $response = $event->getResponse();

        // Act
        $listener($event);

        // Assert
        Assert::assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    #[Test]
    public function does_not_set_csp_or_cache_headers_for_api_doc_endpoint(): void
    {
        // Arrange
        $listener = new SecurityHeadersListener();
        $event = $this->createEvent('/api/doc');
        $response = $event->getResponse();
        $initialCacheControl = $response->headers->get('Cache-Control');

        // Act
        $listener($event);

        // Assert
        Assert::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        Assert::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        Assert::assertFalse($response->headers->has('Content-Security-Policy'));
        Assert::assertSame($initialCacheControl, $response->headers->get('Cache-Control'));
        Assert::assertFalse($response->headers->hasCacheControlDirective('no-store'));
        Assert::assertFalse($response->headers->has('Pragma'));
        Assert::assertFalse($response->headers->has('Expires'));
    }

    #[Test]
    public function keeps_health_endpoint_cacheable_but_applies_api_csp(): void
    {
        // Arrange
        $listener = new SecurityHeadersListener();
        $event = $this->createEvent('/api/health');
        $response = $event->getResponse();
        $initialCacheControl = $response->headers->get('Cache-Control');

        // Act
        $listener($event);

        // Assert
        Assert::assertSame("default-src 'none'; frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        Assert::assertSame($initialCacheControl, $response->headers->get('Cache-Control'));
        Assert::assertFalse($response->headers->hasCacheControlDirective('no-store'));
        Assert::assertFalse($response->headers->has('Pragma'));
        Assert::assertFalse($response->headers->has('Expires'));
    }

    #[Test]
    public function avoids_csp_and_cache_headers_on_profiler_path(): void
    {
        // Arrange
        $listener = new SecurityHeadersListener();
        $event = $this->createEvent('/_profiler/abc123');
        $response = $event->getResponse();
        $initialCacheControl = $response->headers->get('Cache-Control');

        // Act
        $listener($event);

        // Assert
        Assert::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        Assert::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        Assert::assertFalse($response->headers->has('Content-Security-Policy'));
        Assert::assertSame($initialCacheControl, $response->headers->get('Cache-Control'));
        Assert::assertFalse($response->headers->hasCacheControlDirective('no-store'));
    }

    private function createEvent(
        string $path,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
        bool $secure = false,
    ): ResponseEvent {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create($path, 'GET', server: $secure ? ['HTTPS' => 'on'] : []);
        $response = new Response('ok');

        return new ResponseEvent($kernel, $request, $requestType, $response);
    }
}
