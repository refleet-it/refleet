<?php

declare(strict_types=1);

namespace DoctrineMigrations\Qualification;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916100000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add qualifications.archived_at — archiving hides a qualification from the live list and the shift picker without deleting it or its targets';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE qualification.qualifications ADD archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_qualifications_archived_at ON qualification.qualifications (archived_at)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX qualification.idx_qualifications_archived_at');
        $this->addSql('ALTER TABLE qualification.qualifications DROP archived_at');
    }
}
