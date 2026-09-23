<?php

declare(strict_types=1);

namespace DoctrineMigrations\Runner;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260808160000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add runners.api_key_id and runners.archived_at — archiving retires a runner without deleting its history, and revokes exactly the API key that runner authenticated with';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        // No FK to identity.api_keys on purpose: cross-context references are plain ids
        // here, same as organization_id.
        $this->addSql('ALTER TABLE runner.runners ADD api_key_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE runner.runners ADD archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_runners_archived_at ON runner.runners (archived_at)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX runner.idx_runners_archived_at');
        $this->addSql('ALTER TABLE runner.runners DROP archived_at');
        $this->addSql('ALTER TABLE runner.runners DROP api_key_id');
    }
}
