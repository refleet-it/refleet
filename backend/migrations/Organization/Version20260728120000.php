<?php

declare(strict_types=1);

namespace DoctrineMigrations\Organization;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create the organization schema (organizations, employees, gitlab_connections, invitations)';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS organization');
        $this->addSql('CREATE TABLE organization.organizations (id UUID NOT NULL, name VARCHAR(100) NOT NULL, owner_account_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE organization.employees (account_id UUID NOT NULL, email VARCHAR(255) NOT NULL, organization_id UUID DEFAULT NULL, role VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, joined_organization_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (account_id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_employees_email ON organization.employees (email)');
        $this->addSql('CREATE INDEX idx_employees_organization_id ON organization.employees (organization_id)');

        $this->addSql('CREATE TABLE organization.gitlab_connections (id UUID NOT NULL, organization_id UUID NOT NULL, base_url VARCHAR(255) NOT NULL, group_id VARCHAR(32) NOT NULL, group_path VARCHAR(255) NOT NULL, group_name VARCHAR(255) NOT NULL, access_token_ciphertext TEXT NOT NULL, webhook_secret VARCHAR(64) NOT NULL, connected_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, connected_by_account_id UUID NOT NULL, last_synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_sync_status VARCHAR(255) NOT NULL, last_sync_error TEXT DEFAULT NULL, last_sync_project_count INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_gitlab_connections_organization_id ON organization.gitlab_connections (organization_id)');

        $this->addSql('CREATE TABLE organization.invitations (accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, organization_id UUID NOT NULL, email VARCHAR(255) NOT NULL, role VARCHAR(255) NOT NULL, invited_by_account_id UUID NOT NULL, token VARCHAR(255) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_invitations_organization_id ON organization.invitations (organization_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_invitations_token ON organization.invitations (token)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE organization.invitations');
        $this->addSql('DROP TABLE organization.gitlab_connections');
        $this->addSql('DROP TABLE organization.employees');
        $this->addSql('DROP TABLE organization.organizations');
    }
}
