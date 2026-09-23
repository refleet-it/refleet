<?php

declare(strict_types=1);

namespace DoctrineMigrations\Identity;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917090000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Browser-approved CLI logins (refleet login)';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE identity.cli_authorizations (id UUID NOT NULL, status VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, decided_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, user_code VARCHAR(64) NOT NULL, device_secret_hash VARCHAR(64) NOT NULL, runner_name VARCHAR(100) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, account_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5C4385CC9B6B5FBA ON identity.cli_authorizations (account_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cli_authorizations_user_code ON identity.cli_authorizations (user_code)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cli_authorizations_device_secret_hash ON identity.cli_authorizations (device_secret_hash)');
        $this->addSql('ALTER TABLE identity.cli_authorizations ADD CONSTRAINT FK_5C4385CC9B6B5FBA FOREIGN KEY (account_id) REFERENCES identity.accounts (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity.cli_authorizations DROP CONSTRAINT FK_5C4385CC9B6B5FBA');
        $this->addSql('DROP TABLE identity.cli_authorizations');
    }
}
