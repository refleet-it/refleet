<?php

declare(strict_types=1);

namespace DoctrineMigrations\Shift;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916100000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add shifts.archived_at — archiving hides a shift from the live list without deleting it or its targets';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift.shifts ADD archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_shifts_archived_at ON shift.shifts (archived_at)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX shift.idx_shifts_archived_at');
        $this->addSql('ALTER TABLE shift.shifts DROP archived_at');
    }
}
