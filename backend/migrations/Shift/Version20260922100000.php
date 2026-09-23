<?php

declare(strict_types=1);

namespace DoctrineMigrations\Shift;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922100000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Store the composed playbook rules and their sources next to the change prompt';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift.shifts ADD change_rules TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE shift.shifts ADD change_sources JSON DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift.shifts DROP change_sources');
        $this->addSql('ALTER TABLE shift.shifts DROP change_rules');
    }
}
