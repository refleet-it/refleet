<?php

declare(strict_types=1);

namespace DoctrineMigrations\Runner;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921130000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add runners.version (the package version reported on heartbeat) and runners.update_requested_at (a one-shot update request from the dashboard, delivered on the next heartbeat)';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE runner.runners ADD version VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE runner.runners ADD update_requested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE runner.runners DROP version');
        $this->addSql('ALTER TABLE runner.runners DROP update_requested_at');
    }
}
