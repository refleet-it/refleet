<?php

declare(strict_types=1);

namespace DoctrineMigrations\Qualification;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805100100 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create the qualification schema (qualifications, qualification_targets)';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS qualification');

        $this->addSql('CREATE TABLE qualification.qualifications (id UUID NOT NULL, organization_id UUID NOT NULL, created_by UUID NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, criteria_mode VARCHAR(255) NOT NULL, criteria_engine VARCHAR(255) DEFAULT NULL, criteria_target_file VARCHAR(255) DEFAULT NULL, criteria_pattern TEXT DEFAULT NULL, criteria_engine_config JSON DEFAULT NULL, criteria_prompt TEXT DEFAULT NULL, criteria_model VARCHAR(255) DEFAULT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cancel_reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_qualifications_organization_id_status ON qualification.qualifications (organization_id, status)');

        $this->addSql('CREATE TABLE qualification.qualification_targets (id UUID NOT NULL, qualification_id UUID NOT NULL, organization_id UUID NOT NULL, project_id UUID NOT NULL, project_snapshot_external_id VARCHAR(64) NOT NULL, project_snapshot_path VARCHAR(255) NOT NULL, project_snapshot_name VARCHAR(255) NOT NULL, project_snapshot_default_branch VARCHAR(100) DEFAULT NULL, status VARCHAR(255) NOT NULL, summary TEXT DEFAULT NULL, runner_job_id UUID DEFAULT NULL, runner_name VARCHAR(255) DEFAULT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, overridden BOOLEAN DEFAULT false NOT NULL, override_note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_qualification_targets_qualification_project ON qualification.qualification_targets (qualification_id, project_id)');
        $this->addSql('CREATE INDEX idx_qualification_targets_qualification_id_status ON qualification.qualification_targets (qualification_id, status)');
        $this->addSql('CREATE INDEX idx_qualification_targets_organization_id ON qualification.qualification_targets (organization_id)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE qualification.qualification_targets');
        $this->addSql('DROP TABLE qualification.qualifications');
    }
}
