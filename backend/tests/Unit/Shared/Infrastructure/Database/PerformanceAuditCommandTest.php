<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Database;

use App\Shared\Infrastructure\Database\PerformanceAuditCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PerformanceAuditCommand::class)]
final class PerformanceAuditCommandTest extends TestCase
{
    private Connection $connection;

    #[Test]
    public function runs_audit_and_prints_tables_and_slow_queries(): void
    {
        // Given
        $indexUsageResult = $this->createStub(Result::class);
        $indexUsageResult->method('fetchAllAssociative')->willReturn([]); // no unused indexes

        $tableSizesResult = $this->createStub(Result::class);
        $tableSizesResult->method('fetchAllAssociative')->willReturn([
            ['tablename' => 'users', 'size' => '12 MB', 'size_bytes' => 12582912],
            ['tablename' => 'orders', 'size' => '8 MB', 'size_bytes' => 8388608],
        ]);

        $extensionCheckResult = $this->createStub(Result::class);
        $extensionCheckResult->method('fetchOne')->willReturn(1); // extension installed

        $slowQueriesResult = $this->createStub(Result::class);
        $slowQueriesResult->method('fetchAllAssociative')->willReturn([
            [
                'query' => 'SELECT * FROM users WHERE email = $1',
                'calls' => 10,
                'total_exec_time' => 100.0,
                'mean_exec_time' => 10.0,
                'rows' => 10,
            ],
            [
                'query' => 'SELECT * FROM orders WHERE status = $1',
                'calls' => 5,
                'total_exec_time' => 75.0,
                'mean_exec_time' => 15.0,
                'rows' => 5,
            ],
        ]);

        $this->connection
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls(
                $indexUsageResult,
                $tableSizesResult,
                $extensionCheckResult,
                $slowQueriesResult,
            );

        $command = new PerformanceAuditCommand($this->connection);
        $tester = new CommandTester($command);

        // When
        $status = $tester->execute([]);

        // Then
        $display = $tester->getDisplay();

        Assert::assertSame(0, $status);

        // Titles and sections
        Assert::assertStringContainsString('Database Performance Audit', $display);
        Assert::assertStringContainsString('Index Usage Analysis', $display);
        Assert::assertStringContainsString('Table Size Analysis', $display);
        Assert::assertStringContainsString('Query Performance Analysis', $display);
        Assert::assertStringContainsString('Optimization Suggestions', $display);

        // Index usage message when empty
        Assert::assertStringContainsString('All indexes are being used effectively', $display);

        // Table sizes appear in table
        Assert::assertStringContainsString('users', $display);
        Assert::assertStringContainsString('12 MB', $display);
        Assert::assertStringContainsString('orders', $display);
        Assert::assertStringContainsString('8 MB', $display);

        // Slow queries printed
        Assert::assertStringContainsString('Slowest queries:', $display);
        Assert::assertStringContainsString('Query: SELECT * FROM users WHERE email = $1', $display);
        Assert::assertStringContainsString('Calls: 10', $display);
        Assert::assertStringContainsString('Avg time: 10.00ms', $display);

        // Suggestions listing contains a known suggestion
        Assert::assertStringContainsString('Consider pagination for large result sets', $display);

        // Completed
        Assert::assertStringContainsString('Performance audit completed', $display);
    }

    #[Test]
    public function handles_missing_pg_stat_statements_extension(): void
    {
        // Given: first two queries run; extension check returns false
        $indexUsageResult = $this->createStub(Result::class);
        $indexUsageResult->method('fetchAllAssociative')->willReturn([]);

        $tableSizesResult = $this->createStub(Result::class);
        $tableSizesResult->method('fetchAllAssociative')->willReturn([]);

        $extensionCheckResult = $this->createStub(Result::class);
        $extensionCheckResult->method('fetchOne')->willReturn(false);

        $this->connection
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls(
                $indexUsageResult,
                $tableSizesResult,
                $extensionCheckResult,
            );

        $command = new PerformanceAuditCommand($this->connection);
        $tester = new CommandTester($command);

        // When
        $status = $tester->execute([]);

        // Then
        $display = $tester->getDisplay();
        Assert::assertSame(0, $status);
        Assert::assertStringContainsString('pg_stat_statements extension not installed', $display);
        Assert::assertStringContainsString('Performance audit completed', $display);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->connection = $this->createStub(Connection::class);
    }
}
