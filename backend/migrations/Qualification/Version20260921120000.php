<?php

declare(strict_types=1);

namespace DoctrineMigrations\Qualification;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Drop the static (regex) qualification mode: fold existing static criteria into an AI prompt so their history stays readable, drop the target_file/pattern columns, and record the agent\'s 1–5 score per target';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE qualification.qualifications
            SET criteria_prompt = 'Does the file "' || COALESCE(criteria_target_file, '') || '" match the regular expression: ' || COALESCE(criteria_pattern, '') || ' ?'
            WHERE criteria_mode = 'static'
            SQL);
        $this->addSql("UPDATE qualification.qualifications SET criteria_mode = 'ai', criteria_engine = 'claude' WHERE criteria_mode = 'static'");
        $this->addSql("UPDATE qualification.qualifications SET criteria_prompt = '' WHERE criteria_prompt IS NULL");
        $this->addSql('ALTER TABLE qualification.qualifications ALTER criteria_prompt SET NOT NULL');
        $this->addSql('ALTER TABLE qualification.qualifications DROP criteria_target_file');
        $this->addSql('ALTER TABLE qualification.qualifications DROP criteria_pattern');
        $this->addSql('ALTER TABLE qualification.qualification_targets ADD score SMALLINT DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE qualification.qualification_targets DROP score');
        $this->addSql('ALTER TABLE qualification.qualifications ADD criteria_pattern TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE qualification.qualifications ADD criteria_target_file VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE qualification.qualifications ALTER criteria_prompt DROP NOT NULL');
    }
}
