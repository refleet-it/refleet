<?php

declare(strict_types=1);

namespace DoctrineMigrations\Qualification;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Drop qualifications.criteria_engine_config — the php-rector/java-openrewrite static engines it backed have been removed, leaving no engine that uses it';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE qualification.qualifications DROP criteria_engine_config');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE qualification.qualifications ADD criteria_engine_config JSON DEFAULT NULL');
    }
}
