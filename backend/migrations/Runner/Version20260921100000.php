<?php

declare(strict_types=1);

namespace DoctrineMigrations\Runner;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921100000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add runners.usage — what the runner\'s last agent run reported about its consumption (rate-limit windows, context window, tokens, cost), reported on heartbeat and shown on the runner detail page';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE runner.runners ADD usage JSON DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE runner.runners DROP usage');
    }
}
