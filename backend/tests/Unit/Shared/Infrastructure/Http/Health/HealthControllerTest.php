<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http\Health;

use App\Shared\Domain\Service\StorageAvailabilityCheckerInterface;
use App\Shared\Infrastructure\Http\Health\HealthController;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(HealthController::class)]
final class HealthControllerTest extends TestCase
{
    private Connection&MockObject $connection;

    private LoggerInterface&MockObject $logger;

    private StorageAvailabilityCheckerInterface&MockObject $storageChecker;

    #[Test]
    public function returns_healthy_when_database_and_storage_are_reachable(): void
    {
        // Arrange
        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1');

        $this->storageChecker
            ->expects($this->once())
            ->method('isAvailable')
            ->willReturn(true);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $controller = $this->createController();

        // Act
        $response = $controller();

        // Assert
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        Assert::assertSame('healthy', $payload['status'] ?? null);
        Assert::assertSame('1.0.0', $payload['version'] ?? null);
        Assert::assertIsString($payload['timestamp'] ?? null);

        Assert::assertIsArray($payload['checks'] ?? null);

        // Database check
        Assert::assertSame('healthy', $payload['checks']['database']['status'] ?? null);
        Assert::assertSame('active', $payload['checks']['database']['connection'] ?? null);

        // Storage check
        Assert::assertSame('healthy', $payload['checks']['storage']['status'] ?? null);
        Assert::assertSame('active', $payload['checks']['storage']['connection'] ?? null);

        // Memory check (values depend on runtime, assert presence and types)
        Assert::assertIsArray($payload['checks']['memory'] ?? null);
        Assert::assertArrayHasKey('status', $payload['checks']['memory']);
        Assert::assertArrayHasKey('usage_bytes', $payload['checks']['memory']);
        Assert::assertArrayHasKey('usage_mb', $payload['checks']['memory']);
        Assert::assertArrayHasKey('limit', $payload['checks']['memory']);
        Assert::assertIsNumeric($payload['checks']['memory']['usage_bytes']);
        Assert::assertIsNumeric($payload['checks']['memory']['usage_mb']);
        Assert::assertSame(\ini_get('memory_limit'), $payload['checks']['memory']['limit']);
    }

    #[Test]
    public function returns_unhealthy_and_logs_when_database_fails(): void
    {
        // Arrange
        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willThrowException(new \RuntimeException('db down'));

        $this->storageChecker
            ->expects($this->once())
            ->method('isAvailable')
            ->willReturn(true);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Health check database failure',
                $this->callback(static function (array $context): bool {
                    Assert::assertArrayHasKey('error', $context);
                    Assert::assertArrayHasKey('trace', $context);

                    return true;
                }),
            );

        $controller = $this->createController();

        // Act
        $response = $controller();

        // Assert
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());

        $payload = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        Assert::assertSame('unhealthy', $payload['status'] ?? null);
        Assert::assertIsArray($payload['checks'] ?? null);

        // Database check should be marked unhealthy with error info
        Assert::assertSame('unhealthy', $payload['checks']['database']['status'] ?? null);
        Assert::assertSame('Database connection failed', $payload['checks']['database']['error'] ?? null);

        // Memory check still present
        Assert::assertIsArray($payload['checks']['memory'] ?? null);
        Assert::assertArrayHasKey('usage_bytes', $payload['checks']['memory']);
        Assert::assertArrayHasKey('usage_mb', $payload['checks']['memory']);
        Assert::assertArrayHasKey('limit', $payload['checks']['memory']);
    }

    #[Test]
    public function returns_unhealthy_and_logs_when_storage_is_unreachable(): void
    {
        // Arrange
        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1');

        $this->storageChecker
            ->expects($this->once())
            ->method('isAvailable')
            ->willReturn(false);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Health check storage failure');

        $controller = $this->createController();

        // Act
        $response = $controller();

        // Assert
        Assert::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());

        $payload = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        Assert::assertSame('unhealthy', $payload['status'] ?? null);
        Assert::assertSame('unhealthy', $payload['checks']['storage']['status'] ?? null);
        Assert::assertSame('File storage backend unavailable', $payload['checks']['storage']['error'] ?? null);

        // Database check stays healthy - each check is independent
        Assert::assertSame('healthy', $payload['checks']['database']['status'] ?? null);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->storageChecker = $this->createMock(StorageAvailabilityCheckerInterface::class);
    }

    private function createController(): HealthController
    {
        return new HealthController($this->connection, $this->logger, $this->storageChecker);
    }
}
