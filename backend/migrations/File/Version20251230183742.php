<?php

declare(strict_types=1);

namespace DoctrineMigrations\File;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251230183742 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create the file schema (uploaded files, WebP-only storage)';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS file');
        $this->addSql('CREATE TABLE file.files (id UUID NOT NULL, name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(255) NOT NULL, size INT NOT NULL, path VARCHAR(255) NOT NULL, thumbnail_path VARCHAR(255) DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, uploader_id UUID NOT NULL, tenant_id UUID DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE file.files');
    }
}
