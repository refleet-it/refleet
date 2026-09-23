<?php

declare(strict_types=1);

namespace DoctrineMigrations\Runner;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817140000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add runners.supported_models — the model ids a runner was configured to offer for its engine(s), reported on heartbeat and shown on the runner detail page';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE runner.runners ADD supported_models JSON DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE runner.runners DROP supported_models');
    }
}
