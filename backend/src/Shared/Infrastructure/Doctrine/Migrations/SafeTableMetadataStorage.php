<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Metadata\ExecutedMigrationsList;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorage;
use Doctrine\Migrations\Query\Query;
use Doctrine\Migrations\Version\AlphabeticalComparator;
use Doctrine\Migrations\Version\ExecutionResult;

/**
 * Custom TableMetadataStorage wrapper that doesn't fail when table already exists.
 * This is needed for multi-schema setups where different entity managers
 * share the same migrations table.
 */
final readonly class SafeTableMetadataStorage implements MetadataStorage
{
    private MetadataStorage $decorated;

    public function __construct(
        Connection $connection,
    ) {
        // Create the decorated table metadata storage with an alphabetical comparator
        $this->decorated = new TableMetadataStorage($connection, new AlphabeticalComparator());
    }

    #[\Override]
    public function getExecutedMigrations(): ExecutedMigrationsList
    {
        return $this->decorated->getExecutedMigrations();
    }

    #[\Override]
    public function reset(): void
    {
        $this->decorated->reset();
    }

    #[\Override]
    public function complete(ExecutionResult $result): void
    {
        $this->decorated->complete($result);
    }

    /**
     * @return iterable<Query>
     */
    public function getSql(ExecutionResult $result): iterable
    {
        return $this->decorated->getSql($result);
    }

    #[\Override]
    public function ensureInitialized(): void
    {
        try {
            $this->decorated->ensureInitialized();
        } catch (\Throwable $throwable) {
            // Check if it's a "table already exists" error (SQLSTATE 42P07)
            $message = $throwable->getMessage();
            $isDuplicateTable = \str_contains($message, 'already exists')
                || \str_contains($message, '42P07')
                || \str_contains($message, 'Duplicate table');

            if ($isDuplicateTable) {
                // Table already exists, that's fine - just ignore
                // This happens when multiple entity managers try to create the same table
                // or when migrations were partially run before
                return;
            }

            // Re-throw other exceptions
            throw $throwable;
        }
    }
}
