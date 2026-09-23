<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Health;

use App\Shared\Domain\Service\StorageAvailabilityCheckerInterface;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/health', name: 'health_check', methods: ['GET'])]
#[OA\Get(
    description: 'Enhanced health check endpoint for monitoring with database connectivity',
    summary: 'Health Check',
    tags: ['Shared Health'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Service is healthy'),
        new OA\Response(response: Response::HTTP_SERVICE_UNAVAILABLE, description: 'Service is unhealthy'),
    ]
)]
final readonly class HealthController
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
        // Nullable/optional: this controller is shared and loaded by every context's
        // container, but the storage backend is File's alone (StorageAvailabilityCheckerInterface
        // is File's own port — see docs/adr/0001-multiple-kernels.md). Every context but File
        // has no service aliased to this interface at all, so Symfony autowires null rather
        // than failing to build the container; that context simply omits the "storage" check
        // rather than reaching across containers on every health probe to answer a question
        // that isn't about its own health.
        private ?StorageAvailabilityCheckerInterface $storageChecker = null,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'version' => '1.0.0',
            'checks' => [],
        ];

        // Messenger's "to_notification"/"failed" transports run on the same Doctrine
        // connection as the app (MESSENGER_TRANSPORT_DSN=doctrine://default), so the
        // database check below already covers their reachability; no separate check needed
        // unless that DSN is ever pointed at a different broker.
        $health['checks']['database'] = $this->checkDatabase();
        if (null !== $this->storageChecker) {
            $health['checks']['storage'] = $this->checkStorage($this->storageChecker);
        }

        $health['checks']['memory'] = $this->checkMemory();

        $allHealthy = !\in_array('unhealthy', \array_column($health['checks'], 'status'), true);

        if (!$allHealthy) {
            $health['status'] = 'unhealthy';
        }

        $statusCode = 'healthy' === $health['status'] ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return new JsonResponse($health, $statusCode);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDatabase(): array
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return [
                'status' => 'healthy',
                'connection' => 'active',
            ];
        } catch (\Throwable $throwable) {
            $this->logger->error('Health check database failure', [
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            return [
                'status' => 'unhealthy',
                'error' => 'Database connection failed',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkStorage(StorageAvailabilityCheckerInterface $storageChecker): array
    {
        if ($storageChecker->isAvailable()) {
            return [
                'status' => 'healthy',
                'connection' => 'active',
            ];
        }

        $this->logger->error('Health check storage failure');

        return [
            'status' => 'unhealthy',
            'error' => 'File storage backend unavailable',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkMemory(): array
    {
        $memoryUsage = \memory_get_usage(true);
        $memoryLimit = \ini_get('memory_limit');
        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);

        return [
            'status' => $memoryUsage < ($memoryLimitBytes * 0.8) ? 'healthy' : 'warning',
            'usage_bytes' => $memoryUsage,
            'usage_mb' => \round($memoryUsage / 1024 / 1024, 2),
            'limit' => $memoryLimit,
        ];
    }

    private function parseMemoryLimit(string $memoryLimit): int
    {
        if ('-1' === $memoryLimit) {
            return \PHP_INT_MAX;
        }

        $unit = \strtolower(\substr($memoryLimit, -1));
        $value = (int) \substr($memoryLimit, 0, -1);

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
