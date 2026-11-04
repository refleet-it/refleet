<?php

declare(strict_types=1);

namespace DoctrineMigrations\Shift;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return "Watch the pipeline on a target's merge request and track the attempts to fix it";
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        // Both default to off so deploying this starts no pushes: shifts defined before it
        // and merge requests already open are left exactly as they were.
        $this->addSql('ALTER TABLE shift.shifts ADD change_fix_pipelines BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql("ALTER TABLE shift.shift_targets ADD pipeline_status VARCHAR(255) DEFAULT 'not_watched' NOT NULL");

        $this->addSql('ALTER TABLE shift.shift_targets ADD pipeline_head_sha VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE shift.shift_targets ADD pipeline_watch_started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE shift.shift_targets ADD pipeline_checked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE shift.shift_targets ADD pipeline_fix_attempts INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE shift.shift_targets ADD pipeline_fix_job_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE shift.shift_targets ADD pipeline_last_fixed_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE shift.shift_targets ADD pipeline_summary TEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_shift_targets_pipeline_checked_at ON shift.shift_targets (pipeline_status, pipeline_checked_at)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX shift.idx_shift_targets_pipeline_checked_at');
        $this->addSql('ALTER TABLE shift.shift_targets DROP pipeline_summary');
        $this->addSql('ALTER TABLE shift.shift_targets DROP pipeline_last_fixed_id');
        $this->addSql('ALTER TABLE shift.shift_targets DROP pipeline_fix_job_id');
        $this->addSql('ALTER TABLE shift.shift_targets DROP pipeline_fix_attempts');
        $this->addSql('ALTER TABLE shift.shift_targets DROP pipeline_checked_at');
        $this->addSql('ALTER TABLE shift.shift_targets DROP pipeline_watch_started_at');
        $this->addSql('ALTER TABLE shift.shift_targets DROP pipeline_head_sha');
        $this->addSql('ALTER TABLE shift.shift_targets DROP pipeline_status');
        $this->addSql('ALTER TABLE shift.shifts DROP change_fix_pipelines');
    }
}
