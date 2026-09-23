<?php

declare(strict_types=1);

namespace DoctrineMigrations\Shift;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805100200 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create the shift schema (shifts, shift_targets)';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS shift');

        $this->addSql('CREATE TABLE shift.shifts (id UUID NOT NULL, organization_id UUID NOT NULL, created_by UUID NOT NULL, qualification_id UUID DEFAULT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, change_mode VARCHAR(255) DEFAULT NULL, change_engine VARCHAR(255) DEFAULT NULL, change_target_file VARCHAR(255) DEFAULT NULL, change_pattern TEXT DEFAULT NULL, change_replacement TEXT DEFAULT NULL, change_engine_config JSON DEFAULT NULL, change_prompt TEXT DEFAULT NULL, change_model VARCHAR(255) DEFAULT NULL, change_started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cancel_reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_shifts_organization_id_status ON shift.shifts (organization_id, status)');

        $this->addSql('CREATE TABLE shift.shift_targets (id UUID NOT NULL, shift_id UUID NOT NULL, organization_id UUID NOT NULL, project_id UUID NOT NULL, project_snapshot_external_id VARCHAR(64) NOT NULL, project_snapshot_path VARCHAR(255) NOT NULL, project_snapshot_name VARCHAR(255) NOT NULL, project_snapshot_default_branch VARCHAR(100) DEFAULT NULL, status VARCHAR(255) NOT NULL, change_summary TEXT DEFAULT NULL, change_branch_name VARCHAR(255) DEFAULT NULL, runner_job_id UUID DEFAULT NULL, runner_name VARCHAR(255) DEFAULT NULL, change_started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, change_completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, merge_request_url VARCHAR(500) DEFAULT NULL, merge_request_external_iid VARCHAR(64) DEFAULT NULL, merge_request_status VARCHAR(255) NOT NULL, merge_request_opened_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, merge_request_merged_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, merge_request_closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_shift_targets_shift_project ON shift.shift_targets (shift_id, project_id)');
        $this->addSql('CREATE INDEX idx_shift_targets_shift_id_status ON shift.shift_targets (shift_id, status)');
        $this->addSql('CREATE INDEX idx_shift_targets_organization_id ON shift.shift_targets (organization_id)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shift.shift_targets');
        $this->addSql('DROP TABLE shift.shifts');
    }
}
