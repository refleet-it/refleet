<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Purger;

use Doctrine\Common\DataFixtures\Purger\ORMPurgerInterface;
use Doctrine\Common\DataFixtures\Purger\PurgerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

final readonly class MultiEntityManagerPurger implements ORMPurgerInterface, PurgerInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
    ) {
    }

    #[\Override]
    public function setEntityManager(EntityManagerInterface $em): void
    {
        // This method is required by ORMPurgerInterface but not used
        // because we purge all entity managers regardless of which one is set
    }

    #[\Override]
    public function purge(): void
    {
        $entityManagers = [
            'identity',
            'file',
            'notification',
            'organization',
            'project',
            'runner',
            'qualification',
            'shift',
            'playbook',
        ];

        foreach ($entityManagers as $emName) {
            /** @var EntityManagerInterface $em */
            $em = $this->managerRegistry->getManager($emName);
            $this->purgeEntityManager($em);
        }
    }

    private function purgeEntityManager(EntityManagerInterface $em): void
    {
        $connection = $em->getConnection();
        $metadata = $em->getMetadataFactory()->getAllMetadata();

        // Disable foreign key checks
        $connection->executeStatement('SET session_replication_role = replica');

        try {
            foreach ($metadata as $classMetadata) {
                $tableName = $classMetadata->getTableName();
                $schemaName = $classMetadata->getSchemaName();

                $fullTableName = null !== $schemaName ? \sprintf('%s.%s', $schemaName, $tableName) : $tableName;

                // Check if table exists before truncating
                $sql = 'SELECT EXISTS (
                    SELECT FROM information_schema.tables
                    WHERE table_schema = :schema
                    AND table_name = :table
                )';

                $stmt = $connection->prepare($sql);
                $stmt->bindValue('schema', $schemaName ?? 'public');
                $stmt->bindValue('table', $tableName);
                $result = $stmt->executeQuery();

                $exists = $result->fetchOne();
                // PostgreSQL returns 't' or 'f' as string for boolean
                if ('t' === $exists || true === $exists || '1' === $exists || 1 === $exists) {
                    $connection->executeStatement(\sprintf('TRUNCATE TABLE %s CASCADE', $fullTableName));
                }
            }
        } finally {
            // Re-enable foreign key checks
            $connection->executeStatement('SET session_replication_role = DEFAULT');
        }
    }
}
