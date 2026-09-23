<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Listener;

use App\Shared\Domain\Exception\AppException;
use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException as SecurityAccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException as SecurityAuthenticationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(priority: -100)]
final readonly class GlobalExceptionListener
{
    private const int HTTP_BAD_REQUEST = 400;

    private const int HTTP_UNAUTHORIZED = 401;

    private const int HTTP_FORBIDDEN = 403;

    private const int HTTP_UNPROCESSABLE_ENTITY = 422;

    private const int HTTP_INTERNAL_ERROR = 500;

    private const string APP_ENV_DEV = 'dev';

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (null !== $event->getResponse()) {
            return;
        }

        $throwable = $event->getThrowable();
        $this->logException($throwable, $event);
        $response = $this->createErrorResponse($throwable);

        $event->setResponse($response);
    }

    private function logException(\Throwable $throwable, ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $context = [
            'exception' => $throwable::class,
            'message' => $throwable->getMessage(),
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
        ];

        if ($throwable instanceof HttpExceptionInterface) {
            $previous = $throwable->getPrevious();
            if ($previous instanceof DetailedAppException) {
                return;
            }
        }

        // Don't log expected application exceptions at error level
        if ($throwable instanceof AppException
            || $throwable instanceof ValidationFailedException
            || $throwable instanceof SecurityAuthenticationException
            || $throwable instanceof SecurityAccessDeniedException) {
            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            if ($throwable->getStatusCode() >= self::HTTP_INTERNAL_ERROR) {
                $this->logger->error('HTTP error occurred', $context);
            }

            return;
        }

        $context['file'] = $throwable->getFile();
        $context['line'] = $throwable->getLine();
        $this->logger->error('Unexpected exception occurred', $context);
    }

    private function createErrorResponse(\Throwable $throwable): JsonResponse
    {
        if ($throwable instanceof HttpExceptionInterface) {
            $wrappedResponse = $this->resolveWrappedHttpException($throwable);
            if (null !== $wrappedResponse) {
                return $wrappedResponse;
            }
        }

        if ($throwable instanceof DetailedAppException) {
            return new JsonResponse($throwable->toArray(), $this->getAppExceptionHttpStatusCode($throwable));
        }

        if ($throwable instanceof AppException) {
            return $this->buildAppExceptionResponse($throwable);
        }

        if ($throwable instanceof ValidationFailedException) {
            return new JsonResponse([
                'error' => 'VALIDATION_FAILED',
                'message' => 'Data is invalid',
                'details' => $this->formatValidationErrors($throwable),
                'field' => null,
            ], self::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($throwable instanceof HttpExceptionInterface) {
            return new JsonResponse([
                'error' => $this->getErrorCodeFromHttpStatus($throwable->getStatusCode()),
                'message' => $throwable->getMessage(),
                'details' => [],
                'field' => null,
            ], $throwable->getStatusCode());
        }

        if ($throwable instanceof SecurityAuthenticationException) {
            return new JsonResponse(['error' => $this->getErrorCodeFromHttpStatus(self::HTTP_UNAUTHORIZED), 'message' => 'Authentication required', 'details' => [], 'field' => null], self::HTTP_UNAUTHORIZED);
        }

        if ($throwable instanceof SecurityAccessDeniedException) {
            return new JsonResponse(['error' => $this->getErrorCodeFromHttpStatus(self::HTTP_FORBIDDEN), 'message' => 'Access denied', 'details' => [], 'field' => null], self::HTTP_FORBIDDEN);
        }

        return $this->buildInternalErrorResponse($throwable);
    }

    private function resolveWrappedHttpException(HttpExceptionInterface $throwable): ?JsonResponse
    {
        $previous = $throwable->getPrevious();

        if ($previous instanceof DetailedAppException) {
            return new JsonResponse($previous->toArray(), $this->getAppExceptionHttpStatusCode($previous));
        }

        if ($previous instanceof ValidationFailedException) {
            return new JsonResponse([
                'error' => 'VALIDATION_FAILED',
                'message' => 'Data is invalid',
                'details' => $this->formatValidationErrors($previous),
                'field' => null,
            ], self::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    private function buildAppExceptionResponse(AppException $throwable): JsonResponse
    {
        $status = $this->getAppExceptionHttpStatusCode($throwable);

        return new JsonResponse([
            'error' => $this->getErrorCodeFromHttpStatus($status),
            'message' => $throwable->getMessage(),
            'details' => [],
            'field' => null,
        ], $status);
    }

    private function buildInternalErrorResponse(\Throwable $throwable): JsonResponse
    {
        if (isset($_ENV['APP_ENV']) && self::APP_ENV_DEV === $_ENV['APP_ENV']) {
            return new JsonResponse([
                'error' => $this->getErrorCodeFromHttpStatus(self::HTTP_INTERNAL_ERROR),
                'message' => $throwable->getMessage(),
                'details' => ['file' => $throwable->getFile(), 'line' => $throwable->getLine(), 'trace' => $throwable->getTraceAsString()],
                'field' => null,
            ], self::HTTP_INTERNAL_ERROR);
        }

        return new JsonResponse([
            'error' => $this->getErrorCodeFromHttpStatus(self::HTTP_INTERNAL_ERROR),
            'message' => 'An unexpected error occurred',
            'details' => [],
            'field' => null,
        ], self::HTTP_INTERNAL_ERROR);
    }

    private function getAppExceptionHttpStatusCode(AppException $exception): int
    {
        $reflection = new \ReflectionClass($exception);
        $attributes = $reflection->getAttributes(WithHttpStatus::class);

        if ([] !== $attributes) {
            return $attributes[0]->newInstance()->statusCode;
        }

        return self::HTTP_BAD_REQUEST;
    }

    /** @return array<string, array{message: string, code: string}> */
    private function formatValidationErrors(ValidationFailedException $exception): array
    {
        $errors = [];
        foreach ($exception->getViolations() as $violation) {
            $field = $violation->getPropertyPath();
            $code = $violation->getCode();
            $errors[$field] = [
                'message' => (string) $violation->getMessage(),
                'code' => $code ?? '',
            ];
        }

        return $errors;
    }

    private function getErrorCodeFromHttpStatus(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'UNPROCESSABLE_ENTITY',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'INTERNAL_ERROR',
            default => 'UNKNOWN_ERROR',
        };
    }
}
