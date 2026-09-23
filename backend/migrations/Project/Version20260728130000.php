<?php

declare(strict_types=1);

namespace DoctrineMigrations\Project;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728130000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create the project schema (fleet of GitLab-backed microservices)';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS project');
        $this->addSql('CREATE TABLE project.projects (id UUID NOT NULL, organization_id UUID NOT NULL, external_id VARCHAR(32) NOT NULL, name VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, web_url VARCHAR(500) DEFAULT NULL, default_branch VARCHAR(100) DEFAULT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_projects_organization_external_id ON project.projects (organization_id, external_id)');
        $this->addSql('CREATE INDEX idx_projects_organization_id ON project.projects (organization_id)');
        $this->addSql('CREATE INDEX idx_projects_active ON project.projects (organization_id) WHERE archived_at IS NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE project.projects');
    }
}
