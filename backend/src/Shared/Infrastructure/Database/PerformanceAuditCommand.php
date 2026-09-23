<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:db:performance-audit',
    description: 'Audit database performance and suggest optimizations'
)]
final class PerformanceAuditCommand extends Command
{
    private const int TABLE_SIZE_LIMIT = 10;

    private const int SLOW_QUERY_LIMIT = 5;

    private const int QUERY_PREVIEW_LENGTH = 80;

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Database Performance Audit');

        $this->auditIndexUsage($io);
        $this->auditTableSizes($io);
        $this->auditSlowQueries($io);
        $this->suggestOptimizations($io);

        $io->success('Performance audit completed');

        return Command::SUCCESS;
    }

    private function auditIndexUsage(SymfonyStyle $io): void
    {
        $io->section('Index Usage Analysis');

        try {
            $sql = '
                SELECT
                    schemaname,
                    tablename,
                    indexname,
                    idx_tup_read,
                    idx_tup_fetch
                FROM pg_stat_user_indexes
                WHERE idx_tup_read = 0 OR idx_tup_fetch = 0
                ORDER BY tablename, indexname
            ';

            $result = $this->connection->executeQuery($sql)->fetchAllAssociative();

            if ([] === $result) {
                $io->success('All indexes are being used effectively');
            } else {
                $io->warning('Found unused or underused indexes:');
                $io->table(
                    ['Schema', 'Table', 'Index', 'Reads', 'Fetches'],
                    \array_map(\array_values(...), $result)
                );
            }
        } catch (\Throwable $throwable) {
            $io->error('Could not analyze index usage: '.$throwable->getMessage());
        }
    }

    private function auditTableSizes(SymfonyStyle $io): void
    {
        $io->section('Table Size Analysis');

        try {
            $sql = "
                SELECT
                    tablename,
                    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size,
                    pg_total_relation_size(schemaname||'.'||tablename) as size_bytes
                FROM pg_tables
                WHERE schemaname = 'public'
                ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
                LIMIT ".self::TABLE_SIZE_LIMIT.'
            ';

            $result = $this->connection->executeQuery($sql)->fetchAllAssociative();

            $io->table(
                ['Table', 'Size'],
                \array_map(static fn (array $row): array => [
                    \is_string($row['tablename'] ?? null) ? $row['tablename'] : '',
                    \is_string($row['size'] ?? null) ? $row['size'] : '',
                ], $result)
            );
        } catch (\Throwable $throwable) {
            $io->error('Could not analyze table sizes: '.$throwable->getMessage());
        }
    }

    private function auditSlowQueries(SymfonyStyle $io): void
    {
        $io->section('Query Performance Analysis');

        try {
            $extensionExists = $this->connection->executeQuery(
                "SELECT 1 FROM pg_extension WHERE extname = 'pg_stat_statements'"
            )->fetchOne();

            if (false === $extensionExists) {
                $io->note('pg_stat_statements extension not installed. Cannot analyze slow queries.');

                return;
            }

            $sql = "
                SELECT
                    query,
                    calls,
                    total_exec_time,
                    mean_exec_time,
                    rows
                FROM pg_stat_statements
                WHERE query NOT LIKE '%pg_stat_statements%'
                ORDER BY mean_exec_time DESC
                LIMIT ".self::SLOW_QUERY_LIMIT.'
            ';

            $result = $this->connection->executeQuery($sql)->fetchAllAssociative();

            $this->renderSlowQueryResults($io, $result);
        } catch (\Throwable $throwable) {
            $io->error('Could not analyze query performance: '.$throwable->getMessage());
        }
    }

    /**
     * @param array<int, array<string, mixed>> $result
     */
    private function renderSlowQueryResults(SymfonyStyle $io, array $result): void
    {
        if ([] === $result) {
            $io->info('No slow queries found');

            return;
        }

        $io->warning('Slowest queries:');
        foreach ($result as $query) {
            $queryStr = \is_string($query['query'] ?? null) ? $query['query'] : '';
            $calls = \is_numeric($query['calls'] ?? null) ? (int) $query['calls'] : 0;
            $meanTime = \is_numeric($query['mean_exec_time'] ?? null) ? (float) $query['mean_exec_time'] : 0.0;

            $io->text(\sprintf(
                'Query: %s... | Calls: %d | Avg time: %.2fms',
                \substr($queryStr, 0, self::QUERY_PREVIEW_LENGTH),
                $calls,
                $meanTime
            ));
        }
    }

    private function suggestOptimizations(SymfonyStyle $io): void
    {
        $io->section('Optimization Suggestions');

        $suggestions = [
            'Consider adding composite indexes for queries with multiple WHERE conditions',
            'Review JOIN queries and ensure proper indexes exist on join columns',
            'Use EXPLAIN ANALYZE to profile slow queries in development',
            'Consider pagination for large result sets',
            'Use partial indexes for queries with common WHERE clauses',
            'Monitor query execution time in production',
        ];

        $io->listing($suggestions);
    }
}
