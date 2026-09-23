<?php

declare(strict_types=1);

namespace DoctrineMigrations\Playbook;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922100000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create the playbook schema: reusable prompt fragments (tasks and rules) an organization composes shifts and qualifications from';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS playbook');
        $this->addSql('CREATE TABLE playbook.playbooks (id UUID NOT NULL, organization_id UUID NOT NULL, created_by UUID NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, kind VARCHAR(255) NOT NULL, applies_to VARCHAR(255) NOT NULL, body TEXT NOT NULL, is_default BOOLEAN NOT NULL, parameters JSON NOT NULL, engine VARCHAR(255) DEFAULT NULL, model VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_playbooks_organization_id ON playbook.playbooks (organization_id)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE playbook.playbooks');
        $this->addSql('DROP SCHEMA playbook');
    }
}
