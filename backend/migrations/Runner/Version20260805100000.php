<?php

declare(strict_types=1);

namespace DoctrineMigrations\Runner;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805100000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create the runner schema (runners, runner_jobs) — the shared execution infrastructure for Qualification and Shift';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS runner');

        $this->addSql('CREATE TABLE runner.runners (id UUID NOT NULL, organization_id UUID NOT NULL, name VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_runners_organization_id ON runner.runners (organization_id)');

        $this->addSql('CREATE TABLE runner.runner_jobs (id UUID NOT NULL, owner_id UUID NOT NULL, owner_target_id UUID NOT NULL, organization_id UUID NOT NULL, kind VARCHAR(255) NOT NULL, owner_label VARCHAR(255) NOT NULL, mode VARCHAR(255) NOT NULL, payload JSON NOT NULL, engine VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, claimed_by VARCHAR(255) DEFAULT NULL, claimed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, lease_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, attempt_count INT DEFAULT 0 NOT NULL, result_summary TEXT DEFAULT NULL, result_details JSON DEFAULT NULL, error_message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_runner_jobs_owner_target_id ON runner.runner_jobs (owner_target_id)');
        $this->addSql('CREATE INDEX idx_runner_jobs_owner_id ON runner.runner_jobs (owner_id)');
        $this->addSql('CREATE INDEX idx_runner_jobs_claim_lookup ON runner.runner_jobs (organization_id, status, kind, created_at)');
        $this->addSql("CREATE INDEX idx_runner_jobs_lease_expires_at ON runner.runner_jobs (lease_expires_at) WHERE status = 'claimed'");
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE runner.runner_jobs');
        $this->addSql('DROP TABLE runner.runners');
    }
}
