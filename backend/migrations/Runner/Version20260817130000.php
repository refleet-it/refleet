<?php

declare(strict_types=1);

namespace DoctrineMigrations\Runner;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817130000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add runners.supported_engines — the AI-mode engines (claude/kiro) a runner auto-detected on its host, reported on heartbeat and shown on the runner detail page';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE runner.runners ADD supported_engines JSON DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE runner.runners DROP supported_engines');
    }
}
