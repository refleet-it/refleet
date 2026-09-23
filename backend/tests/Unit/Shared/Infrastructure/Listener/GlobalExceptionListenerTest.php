<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Listener;

use App\Shared\Infrastructure\Listener\GlobalExceptionListener;
use App\Tests\Helpers\Shared\Infrastructure\Listener\FakeDetailedException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException as SecurityAccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException as SecurityAuthenticationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[CoversClass(GlobalExceptionListener::class)]
final class GlobalExceptionListenerTest extends TestCase
{
    #[Test]
    public function returns_payload_for_detailed_exception_with_attribute_status(): void
    {
        // Arrange
        $exception = new FakeDetailedException();
        $event = $this->createEvent($exception);
        $listener = $this->createListener();

        // Act
        $listener($event);

        // Assert
        $response = $event->getResponse();
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $data = $this->decode($response);
        Assert::assertSame($exception->toArray(), $data);
    }

    #[Test]
    public function formats_validation_failed_exception_details(): void
    {
        // Arrange
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Invalid email', null, [], null, 'email', 'bad@email', null, 'VIOLATION_EMAIL'),
            new ConstraintViolation('Required field', null, [], null, 'name', null, null, null),
        ]);
        $exception = new ValidationFailedException('payload', $violations);
        $event = $this->createEvent($exception);
        $listener = $this->createListener();

        // Act
        $listener($event);

        // Assert
        $response = $event->getResponse();
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(422, $response->getStatusCode());

        $data = $this->decode($response);
        Assert::assertSame('VALIDATION_FAILED', $data['error'] ?? null);
        Assert::assertSame('Data is invalid', $data['message'] ?? null);
        Assert::assertSame([
            'email' => [
                'message' => 'Invalid email',
                'code' => 'VIOLATION_EMAIL',
            ],
            'name' => [
                'message' => 'Required field',
                'code' => '',
            ],
        ], $data['details'] ?? []);
        Assert::assertNull($data['field'] ?? null);
    }

    #[Test]
    public function maps_http_exception_to_error_code(): void
    {
        // Arrange
        $exception = new HttpException(Response::HTTP_NOT_FOUND, 'Missing resource');
        $event = $this->createEvent($exception);
        $listener = $this->createListener();

        // Act
        $listener($event);

        // Assert
        $response = $event->getResponse();
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $data = $this->decode($response);
        Assert::assertSame('NOT_FOUND', $data['error'] ?? null);
        Assert::assertSame('Missing resource', $data['message'] ?? null);
        Assert::assertSame([], $data['details'] ?? null);
        Assert::assertNull($data['field'] ?? null);
    }

    #[Test]
    public function maps_authentication_exception_to_unauthorized_response(): void
    {
        // Arrange
        $exception = new SecurityAuthenticationException('No token');
        $event = $this->createEvent($exception);
        $listener = $this->createListener();

        // Act
        $listener($event);

        // Assert
        $response = $event->getResponse();
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $data = $this->decode($response);
        Assert::assertSame('UNAUTHORIZED', $data['error'] ?? null);
        Assert::assertSame('Authentication required', $data['message'] ?? null);
        Assert::assertSame([], $data['details'] ?? null);
        Assert::assertNull($data['field'] ?? null);
    }

    #[Test]
    public function maps_access_denied_exception_to_forbidden_response(): void
    {
        // Arrange
        $exception = new SecurityAccessDeniedException('Access blocked');
        $event = $this->createEvent($exception);
        $listener = $this->createListener();

        // Act
        $listener($event);

        // Assert
        $response = $event->getResponse();
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $data = $this->decode($response);
        Assert::assertSame('FORBIDDEN', $data['error'] ?? null);
        Assert::assertSame('Access denied', $data['message'] ?? null);
        Assert::assertSame([], $data['details'] ?? null);
        Assert::assertNull($data['field'] ?? null);
    }

    #[Test]
    public function exposes_debug_details_in_dev_environment(): void
    {
        // Arrange
        $hadEnv = \array_key_exists('APP_ENV', $_ENV);
        $previous = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'dev';

        $exception = new \RuntimeException('Boom');
        $event = $this->createEvent($exception);
        $listener = $this->createListener();

        try {
            // Act
            $listener($event);
        } finally {
            if ($hadEnv) {
                $_ENV['APP_ENV'] = $previous;
            } else {
                unset($_ENV['APP_ENV']);
            }
        }

        // Assert
        $response = $event->getResponse();
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $data = $this->decode($response);
        Assert::assertSame('INTERNAL_ERROR', $data['error'] ?? null);
        Assert::assertSame('Boom', $data['message'] ?? null);
        Assert::assertSame($exception->getFile(), $data['details']['file'] ?? null);
        Assert::assertSame($exception->getLine(), $data['details']['line'] ?? null);
        Assert::assertNotEmpty($data['details']['trace'] ?? null);
        Assert::assertNull($data['field'] ?? null);
    }

    #[Test]
    public function hides_debug_details_outside_dev_environment(): void
    {
        // Arrange
        $hadEnv = \array_key_exists('APP_ENV', $_ENV);
        $previous = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'prod';

        $exception = new \RuntimeException('Hidden');
        $event = $this->createEvent($exception);
        $listener = $this->createListener();

        try {
            // Act
            $listener($event);
        } finally {
            if ($hadEnv) {
                $_ENV['APP_ENV'] = $previous;
            } else {
                unset($_ENV['APP_ENV']);
            }
        }

        // Assert
        $response = $event->getResponse();
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $data = $this->decode($response);
        Assert::assertSame('INTERNAL_ERROR', $data['error'] ?? null);
        Assert::assertSame('An unexpected error occurred', $data['message'] ?? null);
        Assert::assertSame([], $data['details'] ?? null);
        Assert::assertNull($data['field'] ?? null);
    }

    private function createListener(): GlobalExceptionListener
    {
        $logger = $this->createStub(LoggerInterface::class);

        return new GlobalExceptionListener($logger);
    }

    private function createEvent(\Throwable $throwable): ExceptionEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/any', 'GET');

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $throwable);
    }

    /** @return array<string, mixed> */
    private function decode(JsonResponse $response): array
    {
        return \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
